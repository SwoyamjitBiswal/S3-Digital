<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Test - S3 Digital</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-image me-2"></i>Image Loading Test</h4>
                    </div>
                    <div class="card-body">
                        <h5>Testing Product Placeholder Images</h5>
                        <p class="text-muted">Checking if placeholder images load correctly.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>SVG Placeholder Test:</h6>
                                <div class="card-img-container mb-3" style="max-width: 300px;">
                                    <img src="assets/images/product-placeholder.svg" 
                                         class="card-img-top" 
                                         alt="SVG Placeholder"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="product-img-placeholder">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Fallback Test:</h6>
                                <div class="card-img-container mb-3" style="max-width: 300px;">
                                    <img src="assets/images/non-existent.jpg" 
                                         class="card-img-top" 
                                         alt="Non-existent Image"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="product-img-placeholder">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <strong>Expected Results:</strong><br>
                            ✅ SVG placeholder should load with a box icon<br>
                            ✅ Fallback should show the CSS placeholder when image fails<br>
                            ✅ No 404 errors should appear in console
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Back to Homepage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
