<?php
// Footer template
$isAuthPage = in_array($current_page ?? '', ['login.php', 'register.php', 'login_process.php', 'register_process.php']);
if (function_exists('isLoggedIn') && isLoggedIn() && !$isAuthPage):
?>
            </div><!-- /.content-area -->
        </div><!-- /.main-content -->
    </div><!-- /.app-wrapper -->
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base ?? ''; ?>assets/js/app.js"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>


