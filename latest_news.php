<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$page_title = 'Latest News';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<style>
p, li {
    font-size: 16px;
    font-weight: normal;
}
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">

                    <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                        <h4 class="page-title">Latest News</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Latest News</li>
                            </ol>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h4 class="mb-3">Welcome to Lighthouse</h4>
                            
                           <p>The journey from conception to a full-stack accountancy app has taken a little under six months. Currently, Lighthouse is the ideal solution for small businesses and sole traders with a gross turnover under £30k—the exact environment where I use it myself, having successfully migrated from Xero in January 2026.</p>
                            
                            <p>Because Lighthouse is developed in a "live" business environment, its roadmap is driven by real-world needs. However, this also presents a challenge: as my own business is not VAT-registered, the VAT and Making Tax Digital (MTD) modules remain in the HMRC sandbox.</p>
                            
                            <hr class="my3" />
                            
                            <h4 class="mb-3">Community Support</h4>
                            
                            <p>Lighthouse is free, open-source, and built to break the cycle of rising subscription fees. To compete with established services, we need the "wisdom of the crowd"—specifically:</p>
                            
                            <ul>
                                <li><span class="fw-bold text-black">Developers:</span> Those with experience navigating HMRC APIs and production approval processes.</li>
                                <li><span class="fw-bold text-black">Beta Testers:</span> VAT-registered businesses willing to test sandbox modules against real-world scenarios.</li>
                                <li><span class="fw-bold text-black">Advocates:</span> Professionals from all walks of life who believe financial tools should be transparent and user-owned.</li>
                            </ul>
                            
                            <p>Our goal is to make Lighthouse the ideal solution for all business types, sizes, and financial complexities, ensuring that professional-grade accounting remains accessible to everyone, regardless of their budget.</p>
                            
                            <hr class="my3" />
                            
                            <h4 class="mb-3">HMRC Connection: STABLE (Sandbox)</h4>
                            
                            <p>Lighthouse is now successfully communicating with the HMRC API. We can send data and receive success messages in the test environment.</p>
                            
                            <p><span class="fw-bold text-black">The Limitation:</span> To move this to "Production" (filing real VAT returns), we need VAT-registered testers to verify our data mapping against their actual 2026 records.</p>
                            
                            <p>If you are VAT registered and want to help us move out of the Sandbox, please register, download and test Lighthouse, and provide feedback at the community Forum.</p>
                            
                            

                            
                             
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

