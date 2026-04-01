<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT logo_path, company_name FROM business_settings";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = $result['logo_path'] ?? null;
    $companyName = $result['company_name'] ?? 'Lighthouse';
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

?>

<!-- leftbar-tab-menu -->
    <div class="startbar d-print-none">
        <!--start brand-->
        <div class="brand">
            <a href="dashboard.php" class="logo">
            <?php

            if ($logoPath && file_exists($logoPath)) { ?>
                <img src="<?php echo $logoPath; ?>" alt="" class="mb-n4 float-end" height="40">
             <?php } else { ?>
             <h3 class="text-white fw-semibold fs-20 lh-base"><?php echo $companyName; ?></h3>
            <?php } ?>
            </a>
        </div>

        <!--end brand-->
        <!--start startbar-menu-->
        <div class="startbar-menu" >
            <div class="startbar-collapse" id="startbarCollapse" data-simplebar>
                <div class="d-flex align-items-start flex-column w-100">
                    <!-- Navigation -->
                    <ul class="navbar-nav mb-auto w-100">
                        <li class="menu-label mt-2">
                            <span>Main</span>
                        </li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">
                                    <i class="iconoir-user-circle menu-icon"></i>
                                    <span>Login</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="logout.php">
                                    <i class="iconoir-user-circle menu-icon"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="latest_news.php">
                                <i class="iconoir-code menu-icon"></i>
                                <span>Latest News</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="iconoir-presentation menu-icon"></i>
                                <span>Dashboard</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarUserManagement" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarUserManagement">
                                <i class="iconoir-user menu-icon"></i>
                                <span>User Management</span>
                            </a>
                            <div class="collapse " id="sidebarUserManagement">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="user_management.php">Manage Users</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarBusinessSettings" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarBusinessSettings">
                                <i class="iconoir-report-columns menu-icon"></i>
                                <span>Business Settings</span>
                            </a>
                            <div class="collapse " id="sidebarBusinessSettings">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="business_settings.php">Ltd Company</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="sole_trader_settings.php">Sole Trader</a>
                                    </li>
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="system_settings.php">
                                <i class="iconoir-cpu menu-icon"></i>
                                <span>System Settings</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="chart_of_accounts.php">
                                <i class="iconoir-bank menu-icon"></i>
                                <span>Chart of Accounts</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarTransactions" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarTransactions">
                                <i class="iconoir-pound menu-icon"></i>
                                <span>Transactions</span>
                            </a>
                            <div class="collapse " id="sidebarTransactions">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="bank_transactions.php">Bank Transactions</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="bank_import_expenses.php">Import Expenses</a>
                                    </li>
                                     <li class="nav-item">
                                        <a class="nav-link" href="bank_import_income.php">Import Income</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="journal_entries.php">
                                <i class="iconoir-book menu-icon"></i>
                                <span>Journal Entries</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarCustomers" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarCustomers">
                                <i class="iconoir-user-star menu-icon"></i>
                                <span>Customers</span>
                            </a>
                            <div class="collapse " id="sidebarCustomers">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="customers.php">View Customers</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="customer_add.php">Add Customer</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->

                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarInvoices" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarInvoices">
                                <i class="iconoir-receive-pounds menu-icon"></i>
                                <span>Invoices</span>
                            </a>
                            <div class="collapse " id="sidebarInvoices">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="invoices.php">View Invoices</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="customer_select.php">Add Invoice</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->

                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarReports" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarReports">
                                <i class="iconoir-stats-report menu-icon"></i>
                                <span>Reports</span>
                            </a>
                            <div class="collapse " id="sidebarReports">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="report_directors_loan.php">Director Loan Account</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="report_profit_loss.php">Profit / Loss</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="balance_sheet.php">Balance Sheet</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->

                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarNotes" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarNotes">
                                <i class="iconoir-journal menu-icon"></i>
                                <span>Notes</span>
                            </a>
                            <div class="collapse " id="sidebarNotes">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="admin_notes.php">Manage</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="notes.php">View All</a>
                                    </li><!--end nav-item-->
                                    <li class="nav-item">
                                        <a class="nav-link" href="note_create.php">Add New</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->

                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarDiary" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarDiary">
                                <i class="iconoir-calendar menu-icon"></i>
                                <span>Diary</span>
                            </a>
                            <div class="collapse " id="sidebarDiary">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="diary.php">Manage Diary</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->


                        <li class="nav-item">
                            <a class="nav-link" href="#sideTiralBalance" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sideTiralBalance">
                                <i class="iconoir-lock menu-icon"></i>
                                <span>Trial Balance</span>
                            </a>
                            <div class="collapse " id="sideTiralBalance">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="trial_balance.php">Create / View</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div><!--end startbarTables-->
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="tax_digital.php">
                                <i class="iconoir-hand-card menu-icon"></i>
                                <span>MTD</span>
                            </a>
                        </li><!--end nav-item-->
                        <li class="nav-item">
                            <a class="nav-link" href="vat_return.php">
                                <i class="iconoir-hand-card menu-icon"></i>
                                <span>VAT Return</span>
                            </a>
                        </li><!--end nav-item-->
                    </ul><!--end navbar-nav--->
                </div>
            </div><!--end startbar-collapse-->
        </div><!--end startbar-menu-->    
    </div><!--end startbar-->
    <div class="startbar-overlay d-print-none"></div>
    <!-- end leftbar-tab-menu-->