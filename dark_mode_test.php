<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Mode Test - S3 Digital</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body>
    <!-- Test Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-store me-2"></i>S3 Digital
            </a>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" title="Toggle dark mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Test Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-moon me-2"></i>Dark Mode Test</h4>
                    </div>
                    <div class="card-body">
                        <h5>Testing Dark Theme Functionality</h5>
                        <p class="text-muted">Click the moon/sun icon in the navbar to toggle dark mode.</p>
                        
                        <div class="alert alert-info">
                            <strong>Test Instructions:</strong><br>
                            1. Click the moon icon 🌙 in the navbar<br>
                            2. The page should switch to dark theme<br>
                            3. The icon should change to sun ☀️<br>
                            4. Click again to switch back to light theme
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Test Card 1</h6>
                                        <p class="card-text">This card should change colors in dark mode.</p>
                                        <button class="btn btn-primary">Primary Button</button>
                                        <button class="btn btn-outline-primary ms-2">Outline Button</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Test Card 2</h6>
                                        <p class="card-text">Background should be dark in dark mode.</p>
                                        <span class="badge bg-success">Success Badge</span>
                                        <span class="badge bg-warning ms-2">Warning Badge</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Form Test</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" class="form-control mb-3" placeholder="Test input field">
                                    <select class="form-select">
                                        <option>Test dropdown</option>
                                        <option>Option 2</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <textarea class="form-control" rows="3" placeholder="Test textarea"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Table Test</h6>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Column 1</th>
                                        <th>Column 2</th>
                                        <th>Column 3</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Row 1 Data</td>
                                        <td>Row 1 Data</td>
                                        <td>Row 1 Data</td>
                                    </tr>
                                    <tr>
                                        <td>Row 2 Data</td>
                                        <td>Row 2 Data</td>
                                        <td>Row 2 Data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-home me-2"></i>Go to Homepage
                    </a>
                    <a href="project_status.php" class="btn btn-info ms-2">
                        <i class="fas fa-chart-line me-2"></i>Project Status
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
