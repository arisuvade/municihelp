<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../includes/auth/login.php');
    exit;
}

$pageTitle = "MSWD - PWD Form";
include '../../includes/header.php';

// Hardcoded program data for PWD walk-in
$programs = [
    [
        'id' => 1,
        'name' => 'Financial Assistance',
        'sub_programs' => [
            [
                'id' => 101,
                'name' => 'Birthday Cash Give',
                'requirements' => [
                    'Registered PWD ID'
                ]
            ],
            [
                'id' => 102,
                'name' => 'In-School PWD',
                'requirements' => [
                    'PWD ID',
                    'Certificate of Enrollment',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 103,
                'name' => 'SPED',
                'requirements' => [
                    'PWD ID',
                    'Certificate of Enrollment',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 104,
                'name' => 'Alay ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 105,
                'name' => 'Malasakit ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Certificate of therapy',
                    'Parent ID'
                ]
            ],
            [
                'id' => 106,
                'name' => 'Kalinga ni MOM',
                'requirements' => [
                    'PWD ID',
                    'Student ID',
                    'Barangay Indigency'
                ]
            ],
            [
                'id' => 107,
                'name' => 'Burial',
                'requirements' => [
                    'Death certificate'
                ]
            ]
        ]
    ]
];

// Fetch barangays
$barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="../../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --munici-green: #2C80C6;
            --munici-green-light: #42A5F5;
            --pending: #FFC107;
            --approved: #28A745;
            --completed: #4361ee;
            --declined: #DC3545;
            --cancelled: #6C757D;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            width: 95%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--munici-green), #0E3B85);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .request-form-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .request-form-card .card-header {
            text-align: center;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .btn-submit {
            background-color: var(--munici-green);
            color: white;
            padding: 12px 25px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            border-radius: 8px;
        }
        
        .btn-submit:hover {
            background-color: #0E3B85;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .file-upload-container {
            border: 2px dashed #ddd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        
        .file-upload-container:hover {
            border-color: var(--munici-green);
        }
        
        .file-upload-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            margin: 10px auto 0;
            display: none;
        }
        
        .program-group-title {
            font-weight: bold;
            color: var(--munici-green);
            padding: 5px 10px;
            background-color: #f0f0f0;
            margin-top: 10px;
            border-radius: 5px;
        }
        
        .offline-notice {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Mobile specific styles */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 10px;
                width: 100%;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            
            .dashboard-header p {
                font-size: 0.9rem;
            }
            
            .form-section h5 {
                font-size: 1.1rem;
            }
            
            .btn-submit {
                width: 100%;
                padding: 12px;
            }
            
            .row.g-2 > [class^="col-"] {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">PWD Request</h1>
                    <p class="mb-0">For assistance, please visit us in person</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="request-form-card card">
            <div class="card-header">
                PWD Request Form (Walk-in Only)
            </div>
            <div class="card-body">
                <div class="alert-warning">
                    <strong>Paunawa:</strong> Ang lahat ng PWD programs ay kailangang asikasuhin nang personal sa opisina. Mangyaring dalhin ang lahat ng kinakailangang dokumento.
                </div>
                
                <!-- Program Selection Form -->
                <form id="programSelectionForm">
                    <div class="form-section">
                        <h5 class="mb-4">Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="program" name="assistance_id" required>
                                    <option value="" selected disabled>Select a program</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?= $program['id'] ?>">
                                            <?= htmlspecialchars($program['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Program Selection -->
                    <div class="form-section" id="subProgramSection" style="display: none;">
                        <h5 class="mb-4">Sub-Program</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <select class="form-select" id="subProgram" name="sub_program_id">
                                    <option value="" selected disabled>Select a sub-program</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Walk-in Information Section -->
<div id="walkinInfoSection" style="display: none;">
    <div class="form-section">
        <h5 class="mb-4">Walk-in Procedure</h5>
        <div class="alert">
            <p>Ang programang ito ay kailangang asikasuhin nang personal. Narito ang mga kailangang dalhin at proseso:</p>
            <ul id="walkinRequirementsList"></ul>
            <p class="mt-3"><strong>Lokasyon ng Opisina:</strong> Former PUP, Pulilan, Bulacan</p>
            <p><strong>Oras ng Opisina:</strong> Lunes hanggang Biyernes, 8:00 AM hanggang 5:00 PM</p>
            
            <!-- Updated Contact Information Section -->
            <div class="contact-options mt-3">
                <p><strong>Para sa mga katanungan:</strong></p>
                <ul>
                    <li>Maaaring <a href="../../user/inquiry.php" class="text-primary">magsumite ng inquiry</a> online (piliin ang angkop na department)</li>
                    <li>Magpunta sa opisina para sa direktang asistensya</li>
                </ul>
            </div>
        </div>
        <div class="offline-notice">
            <strong>Paunawa:</strong> Ang pagkuha ng programang ito ay hindi po maaaring gawin online. Kailangan po itong personal na asikasuhin. Salamat po sa inyong pang-unawa.
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        const programs = <?php echo json_encode($programs); ?>;
        
        // Program selection change handler
        $('#program').change(function() {
            const selectedProgramId = $(this).val();
            const selectedProgram = programs.find(p => p.id == selectedProgramId);
            
            // Reset and hide sections
            $('#subProgramSection').hide();
            $('#walkinInfoSection').hide();
            
            if (selectedProgram) {
                // Check if program has sub-programs
                if (selectedProgram.sub_programs && selectedProgram.sub_programs.length > 0) {
                    $('#subProgramSection').show();
                    $('#subProgram').empty().append('<option value="" selected disabled>Select a sub-program</option>');
                    
                    // Populate sub-programs
                    selectedProgram.sub_programs.forEach(sub => {
                        $('#subProgram').append(`<option value="${sub.id}">${sub.name}</option>`);
                    });
                } else {
                    // If no sub-programs, show walk-in info directly
                    showWalkinInfo(selectedProgram);
                }
            }
        });
        
        // Sub-program selection change handler
        $('#subProgram').change(function() {
            const selectedSubProgramId = $(this).val();
            const selectedProgramId = $('#program').val();
            
            if (selectedSubProgramId) {
                const selectedProgram = programs.find(p => p.id == selectedProgramId);
                if (selectedProgram) {
                    const selectedSub = selectedProgram.sub_programs.find(sub => sub.id == selectedSubProgramId);
                    if (selectedSub) {
                        showWalkinInfo(selectedSub);
                    }
                }
            } else {
                $('#walkinInfoSection').hide();
            }
        });
        
        // Function to show walk-in information
        function showWalkinInfo(program) {
            const container = $('#walkinRequirementsList');
            container.empty();
            
            if (program.requirements && program.requirements.length > 0) {
                program.requirements.forEach(req => {
                    container.append(`<li>${req}</li>`);
                });
            } else {
                container.append('<li>Walang partikular na nakalista na mga kinakailangan</li>');
            }
            
            $('#walkinInfoSection').show();
        }
    });
    </script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>