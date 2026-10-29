<?php
/**
 * Footer Template
 * Academics Student Management System
 */
if (function_exists('isLoggedIn') && isLoggedIn() && empty($is_auth) && empty($is_public)):
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
