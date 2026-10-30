<?php
/**
 * Web Push (VAPID) helpers for Academics.
 *
 * Sends browser push notifications to subscribers of a school when an
 * emergency alert is activated. Payload is encrypted per RFC 8291.
 */

if (!function_exists('push_openssl_conf')) {
    function push_openssl_conf(): string
    {
        $candidates = [
            'F:/Xampp/php/extras/openssl/openssl.cnf',
            'F:/Xampp/php/extras/ssl/openssl.cnf',
            'F:/Xampp/apache/conf/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        return '';
    }
}

if (!function_exists('push_ensure_openssl')) {
    function push_ensure_openssl(): void
    {
        $conf = push_openssl_conf();
        if ($conf !== '' && getenv('OPENSSL_CONF') === false) {
            putenv('OPENSSL_CONF=' . $conf);
        }
    }
}

if (!function_exists('push_b64url_encode')) {
    function push_b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('push_b64url_decode')) {
    function push_b64url_decode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('push_ensure_vapid')) {
    /**
     * Returns ['public' => base64url, 'private_pem' => string, 'subject' => string].
     * Generates and persists keys in application_settings on first call.
     */
    function push_ensure_vapid(PDO $pdo): array
    {
        push_ensure_openssl();
        $keys = ['public' => '', 'private_pem' => '', 'subject' => 'mailto:support@academics.app'];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM application_settings WHERE setting_key IN ('vapid_public_key','vapid_private_key','vapid_subject')");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['setting_key'] === 'vapid_public_key') {
                    $keys['public'] = (string)$r['setting_value'];
                } elseif ($r['setting_key'] === 'vapid_private_key') {
                    $keys['private_pem'] = (string)$r['setting_value'];
                } elseif ($r['setting_key'] === 'vapid_subject') {
                    $keys['subject'] = (string)$r['setting_value'] ?: $keys['subject'];
                }
            }
        } catch (Throwable $e) {
            // table may not exist; ignore
        }

        if ($keys['public'] !== '' && $keys['private_pem'] !== '') {
            return $keys;
        }

        // Generate new P-256 key pair.
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'config' => push_openssl_conf()]);
        if ($res === false) {
            throw new RuntimeException('Unable to generate VAPID key: ' . openssl_error_string());
        }
        openssl_pkey_export($res, $privPem, null, ['config' => push_openssl_conf()]);
        $details = openssl_pkey_get_details($res);
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $pubRaw = "\x04" . $x . $y;
        $public = push_b64url_encode($pubRaw);

        // Persist.
        try {
            $upsert = $pdo->prepare("INSERT INTO application_settings (setting_key, setting_value, description) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $upsert->execute(['vapid_public_key', $public, 'VAPID public key (base64url)']);
            $upsert->execute(['vapid_private_key', $privPem, 'VAPID private key (PEM)']);
            $upsert->execute(['vapid_subject', $keys['subject'], 'VAPID subject (mailto)']);
        } catch (Throwable $e) {
            // ignore persistence failure; keys still usable this request
        }

        $keys['public'] = $public;
        $keys['private_pem'] = $privPem;
        return $keys;
    }
}

if (!function_exists('push_der_to_raw')) {
    function push_der_to_raw(string $der): string
    {
        // Convert DER-encoded ECDSA signature to raw R||S (64 bytes for P-256).
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) {
            return '';
        }
        // length
        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x7f;
            $len = 0;
            for ($i = 0; $i < $n; $i++) {
                $len = ($len << 8) | ord($der[$pos++]);
            }
        }
        // SEQUENCE of two INTEGERs
        $raw = '';
        for ($k = 0; $k < 2; $k++) {
            if (ord($der[$pos++]) !== 0x02) {
                return '';
            }
            $l = ord($der[$pos++]);
            if ($l & 0x80) {
                $n = $l & 0x7f;
                $l = 0;
                for ($i = 0; $i < $n; $i++) {
                    $l = ($l << 8) | ord($der[$pos++]);
                }
            }
            $val = substr($der, $pos, $l);
            $pos += $l;
            // strip leading zero
            if (strlen($val) > 32 && $val[0] === "\x00") {
                $val = substr($val, 1);
            }
            $val = str_pad($val, 32, "\x00", STR_PAD_LEFT);
            $raw .= $val;
        }
        return $raw;
    }
}

if (!function_exists('push_vapid_jwt')) {
    function push_vapid_jwt(array $vapid, string $audience): string
    {
        push_ensure_openssl();
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $vapid['subject'],
        ];
        $segments = [
            push_b64url_encode(json_encode($header)),
            push_b64url_encode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $privKey = openssl_pkey_get_private($vapid['private_pem']);
        if ($privKey === false) {
            throw new RuntimeException('Invalid VAPID private key');
        }
        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID signing failed');
        }
        $rawSig = push_der_to_raw($signature);
        if (strlen($rawSig) !== 64) {
            throw new RuntimeException('Bad VAPID signature length');
        }
        $segments[] = push_b64url_encode($rawSig);
        return implode('.', $segments);
    }
}

if (!function_exists('push_hkdf_expand')) {
    function push_hkdf_expand(string $prk, string $info, int $length): string
    {
        $t = '';
        $last = '';
        $blocks = (int)ceil($length / 32);
        for ($i = 1; $i <= $blocks; $i++) {
            $last = hash_hmac('sha256', $last . $info . chr($i), $prk, true);
            $t .= $last;
        }
        return substr($t, 0, $length);
    }
}

if (!function_exists('push_encrypt_payload')) {
    /**
     * Returns ['body' => binary encrypted body, 'headers' => array of extra headers].
     */
    function push_encrypt_payload(string $plaintext, string $p256dh, string $auth): string
    {
        push_ensure_openssl();
        $uaPublic = push_b64url_decode($p256dh);
        $authSecret = push_b64url_decode($auth);

        // salt
        $salt = random_bytes(16);

        // local key pair
        $localKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'config' => push_openssl_conf()]);
        if ($localKey === false) {
            throw new RuntimeException('local key gen failed');
        }
        $localDetails = openssl_pkey_get_details($localKey);
        $localPublic = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

        $uaPem = push_raw_public_to_pem($uaPublic);
        $sharedSecret = openssl_pkey_derive(openssl_pkey_get_public($uaPem), $localKey);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH failed');
        }

        // PRK = HMAC(auth, sharedSecret)
        $prk = hash_hmac('sha256', $sharedSecret, $authSecret, true);

        $context = "P-256\x00" . pack('n', strlen($uaPublic)) . $uaPublic . pack('n', strlen($localPublic)) . $localPublic;

        $cek = push_hkdf_expand($prk, "Content-Encoding: aes128gcm\x00" . $context, 16);
        $nonce = push_hkdf_expand($prk, "Content-Encoding: nonce\x00" . $context, 12);

        // Pad plaintext with delimiter 0x02
        $padded = $plaintext . "\x02";

        $tag = '';
        $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('aes encrypt failed');
        }

        // aes128gcm header
        $body = $salt . pack('N', 4096) . chr(strlen($localPublic)) . $localPublic . $ciphertext . $tag;
        return $body;
    }
}

if (!function_exists('push_raw_public_to_pem')) {
    function push_raw_public_to_pem(string $raw): string
    {
        // Wrap raw 65-byte uncompressed point in SubjectPublicKeyInfo for P-256.
        // Use openssl to import via EC key? Simpler: build PEM with ASN.1.
        // We'll use a template: openssl_pkey_get_public accepts PEM only, so build PEM.
        // Use phpseclib? Not available. Implement minimal ASN.1 encoding.
        $asn1 = push_asn1_ec_public_key($raw);
        $b64 = base64_encode($asn1);
        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split($b64, 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";
        return $pem;
    }
}

if (!function_exists('push_asn1_ec_public_key')) {
    function push_asn1_ec_public_key(string $raw): string
    {
        // SubjectPublicKeyInfo for P-256:
        // SEQUENCE { AlgorithmIdentifier { OID ecPublicKey, OID prime256v1 }, BIT STRING (0x00 + raw) }
        $oidEc = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // 1.2.840.10045.2.1
        $oidCurve = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // 1.2.840.10045.3.1.7
        $algoId = push_asn1_seq($oidEc . $oidCurve);
        $bitString = "\x03" . push_asn1_len(strlen($raw) + 1) . "\x00" . $raw;
        return push_asn1_seq($algoId . $bitString);
    }
}

if (!function_exists('push_asn1_len')) {
    function push_asn1_len(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $out = '';
        $tmp = $len;
        while ($tmp > 0) {
            $out = chr($tmp & 0xff) . $out;
            $tmp >>= 8;
        }
        return chr(0x80 | strlen($out)) . $out;
    }
}

if (!function_exists('push_asn1_seq')) {
    function push_asn1_seq(string $content): string
    {
        return "\x30" . push_asn1_len(strlen($content)) . $content;
    }
}

if (!function_exists('push_save_subscription')) {
    function push_save_subscription(PDO $pdo, int $userId, ?int $schoolId, array $sub): bool
    {
        $endpoint = (string)($sub['endpoint'] ?? '');
        $p256dh = (string)($sub['keys']['p256dh'] ?? '');
        $auth = (string)($sub['keys']['auth'] ?? '');
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return false;
        }
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        try {
            $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, school_id, endpoint, p256dh, auth, user_agent)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), school_id=VALUES(school_id), p256dh=VALUES(p256dh), auth=VALUES(auth), user_agent=VALUES(user_agent), updated_at=NOW()");
            $stmt->execute([$userId, $schoolId, $endpoint, $p256dh, $auth, $ua]);
            return true;
        } catch (Throwable $e) {
            error_log('push_save_subscription: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('push_remove_subscription')) {
    function push_remove_subscription(PDO $pdo, string $endpoint): void
    {
        try {
            $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$endpoint]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('push_send_to_school')) {
    /**
     * Send a push notification to all subscribers of a school (or platform-wide if schoolId null).
     */
    function push_send_to_school(PDO $pdo, ?int $schoolId, string $title, string $body, string $url = '/'): array
    {
        push_ensure_openssl();
        $vapid = push_ensure_vapid($pdo);

        $sql = "SELECT * FROM push_subscriptions WHERE 1=1";
        $params = [];
        if ($schoolId !== null) {
            $sql .= " AND school_id = ?";
            $params[] = $schoolId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $failed = 0;
        foreach ($subs as $sub) {
            try {
                $endpoint = (string)$sub['endpoint'];
                $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
                $jwt = push_vapid_jwt($vapid, $audience);

                $payload = json_encode([
                    'title' => $title,
                    'body'  => $body,
                    'url'   => $url,
                    'icon'  => '/assets/img/academics-mark.svg',
                    'tag'   => 'academics-alert-' . ($schoolId ?? 0),
                ], JSON_UNESCAPED_UNICODE);

                $encrypted = push_encrypt_payload($payload, (string)$sub['p256dh'], (string)$sub['auth']);

                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $encrypted,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/octet-stream',
                        'Content-Encoding: aes128gcm',
                        'TTL: 86400',
                        'Urgency: high',
                        'Authorization: vapid t=' . $jwt . ', k=' . $vapid['public'],
                    ],
                ]);
                $response = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code >= 200 && $code < 300) {
                    $sent++;
                } else {
                    $failed++;
                    if ($code === 404 || $code === 410) {
                        push_remove_subscription($pdo, $endpoint);
                    }
                    error_log("push send failed HTTP $code: " . substr((string)$response, 0, 200));
                }
            } catch (Throwable $e) {
                $failed++;
                error_log('push_send_to_school: ' . $e->getMessage());
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'total' => count($subs)];
    }
}
