<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Server Error | <?php echo defined('APP_NAME') ? APP_NAME : 'Academics'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/edustudy-theme.css">
    <script>
    (function(){try{var t=localStorage.getItem('es_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();
    </script>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card text-center" style="max-width: 500px;">
            <div class="auth-body py-5">
                <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                <h1 class="display-4 fw-bold">500</h1>
                <h4 class="mb-3">Internal Server Error</h4>
                <p class="text-muted mb-4">Something went wrong on our end. Please try again later.</p>
                <a href="../dashboard.php" class="btn btn-primary"><i class="fas fa-home me-1"></i> Go to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
