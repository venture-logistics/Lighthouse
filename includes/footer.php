


<?php

$stmt = $pdo->prepare("SELECT company_name FROM business_settings");
$stmt->execute();
$company_name = $stmt->fetchColumn();

?>




     <footer class="bg-light py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo $company_name; ?></p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a target="_blank" href="https://video.lighthousefinance.io/" class="text-decoration-none me-3">Video Guides</a>
                    <a target="_blank" href="https://support.lighthousefinance.io/" class="text-decoration-none me-3">Get Support</a>
                </div>
            </div>
        </div>
    </footer>
  

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
    <script src="assets/js/pages/transactions.init.js"></script>
    <script src="assets/js/index.init.js"></script>
    <script src="assets/js/DynamicSelect.js"></script>
    <script src="assets/js/app.js"></script>

</body>
</html>