<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../includes/auth/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: status.php');
    exit;
}

$request_id = (int)$_GET['id'];

// get request details
$stmt = $conn->prepare("
    SELECT r.*, a.name as assistance_name, a.specific_requirement, b.name as barangay_name
    FROM assistance_requests r
    JOIN assistance_types a ON r.assistance_id = a.id
    JOIN barangays b ON r.barangay_id = b.id
    WHERE r.id = ? AND r.user_id = ? AND r.status = 'pending'
");
$stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    $_SESSION['error'] = "Request not found or not editable";
    header('Location: status.php');
    exit;
}

// get assistance types and barangays
$assistance_types = $conn->query("SELECT * FROM assistance_types ORDER BY 
    CASE 
        WHEN name = 'Medical Assistance' THEN 1
        WHEN name = 'Nebulizer Request' THEN 2
        WHEN name = 'Glucometer Request' THEN 3
        WHEN name = 'Wheelchair Request' THEN 4
        WHEN name = 'Laboratory Assistance' THEN 5
        WHEN name = 'Burial Assistance' THEN 6
        WHEN name = 'Educational Assistance' THEN 7
        ELSE 8
    END")->fetch_all(MYSQLI_ASSOC);

$barangays = $conn->query("SELECT * FROM barangays ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title = "Edit Request";
include '../includes/header.php';

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // validate required fields
        $required = ['assistance_id', 'first_name', 'last_name', 'barangay_id', 'complete_address'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled.");
            }
        }

        // initialize file paths with existing values
        $file_paths = [
            'specific_request_path' => $request['specific_request_path'],
            'indigency_cert_path' => $request['indigency_cert_path'],
            'id_copy_path' => $request['id_copy_path'],
            'request_letter_path' => $request['request_letter_path']
        ];

        // process file uploads
        $file_fields = [
            'specific_request' => ['db_field' => 'specific_request_path', 'number' => 1],
            'indigency_cert' => ['db_field' => 'indigency_cert_path', 'number' => 2],
            'id_copy' => ['db_field' => 'id_copy_path', 'number' => 3],
            'request_letter' => ['db_field' => 'request_letter_path', 'number' => 4]
        ];

        // prepare filename components
        $userId = $_SESSION['user_id'];
        
        // format assistance name
        $assistanceName = str_replace(' Request', '', $request['assistance_name']);
        $assistanceName = preg_replace('/[^a-zA-Z0-9]/', '', $assistanceName);
        
        $namePart = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['last_name'] . $_POST['first_name']);
        $barangayName = preg_replace('/[^a-zA-Z0-9]/', '', $request['barangay_name']);
        $dateTimePart = date('YmdHis');

        foreach ($file_fields as $file_input => $file_data) {
            if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == UPLOAD_ERR_OK) {
                if ($_FILES[$file_input]['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File size exceeds 5MB limit.");
                }

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                if (!in_array($_FILES[$file_input]['type'], $allowedTypes)) {
                    throw new Exception("Only JPG, PNG, and PDF files are allowed.");
                }

                $uploadDir = '../uploads/vm_rj_assistance/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = pathinfo($_FILES[$file_input]['name'], PATHINFO_EXTENSION);
                
                // create filename with the same format as submission
                $filename = sprintf(
                    "%d_%d_%d_%s_Request_%s_%s_%s.%s",
                    $request_id,
                    $userId,
                    $file_data['number'],
                    $assistanceName,
                    $namePart,
                    $barangayName,
                    $dateTimePart,
                    $ext
                );
                
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $destination)) {
                    // delete old file if it exists
                    if (file_exists('../' . $file_paths[$file_data['db_field']])) {
                        @unlink('../' . $file_paths[$file_data['db_field']]);
                    }
                    $file_paths[$file_data['db_field']] = 'uploads/vm_rj_assistance/' . $filename;
                } else {
                    throw new Exception("Failed to upload document.");
                }
            }
        }

        // update request in database
        $sql = "UPDATE assistance_requests SET
                assistance_id = ?,
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                barangay_id = ?,
                complete_address = ?,
                specific_request_path = ?,
                indigency_cert_path = ?,
                id_copy_path = ?,
                request_letter_path = ?
            WHERE id = ? AND user_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $assistance_id = $_POST['assistance_id'];
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'] ?? '';
        $last_name = $_POST['last_name'];
        $barangay_id = $_POST['barangay_id'];
        $complete_address = $_POST['complete_address'];

        // bind parameters
        $stmt->bind_param(
            "isssisssssii", 
            $assistance_id,       
            $first_name,         
            $middle_name,        
            $last_name,         
            $barangay_id,        
            $complete_address,   
            $file_paths['specific_request_path'],  
            $file_paths['indigency_cert_path'],    
            $file_paths['id_copy_path'],         
            $file_paths['request_letter_path'],
            $request_id,         
            $_SESSION['user_id']  
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Request updated successfully";
            header('Location: status.php');
            exit;
        } else {
            throw new Exception("Failed to update request: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>


<style>
    :root {
        --munici-green: #4CAF50;
        --munici-green-light: #E8F5E9;
    }
    
    body {
        background-color: #f5f7fb;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .dashboard-header {
        background: linear-gradient(135deg, var(--munici-green), #2E7D32);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .request-form-card {
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border: none;
        margin-bottom: 2rem;
    }
    
    .request-form-card .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        font-size: 1.2rem;
        padding: 1.25rem 1.5rem;
        border-radius: 10px 10px 0 0 !important;
    }
    
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
    }
    
    .document-upload {
        border: 2px dashed #ddd;
        padding: 1.5rem;
        text-align: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 1rem;
    }
    
    .document-upload:hover {
        border-color: var(--munici-green);
        background-color: rgba(76, 175, 80, 0.05);
    }
    
    .document-preview {
        max-width: 100%;
        max-height: 200px;
        margin-top: 1rem;
        display: none;
    }
    
    .btn-submit {
        background-color: var(--munici-green);
        color: white;
        padding: 10px 25px;
        font-weight: 600;
    }
    
    .required-field::after {
        content: " *";
        color: red;
    }
</style>

<div class="dashboard-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">Edit Assistance Request</h1>
                <p class="mb-0">I-update ang mga detalye ng iyong kahilingan.</p>
            </div>
            <button class="btn btn-light" onclick="location.href='status.php'">
                <i class="fas fa-arrow-left"></i> Back to Status
            </button>
        </div>
    </div>
</div>

<div class="container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="request-form-card card">
        <div class="card-header">
            Edit Request #<?= $request['id'] ?>
        </div>
        <div class="card-body">
            <form id="editRequestForm" method="POST" enctype="multipart/form-data">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h5 class="mb-4">Personal na impormasyon ng nangangailangan</h5>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label required-field">Unang Pangalan (First Name)</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($request['first_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Gitnang Pangalan (Middle Name)</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name"
                                   value="<?= htmlspecialchars($request['middle_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label required-field">Apelyido (Last Name)</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?= htmlspecialchars($request['last_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="barangay" class="form-label required-field">Barangay</label>
                            <select class="form-select" id="barangay" name="barangay_id" required>
                                <option value="" disabled>Select barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['id'] ?>" 
                                        <?= $barangay['id'] == $request['barangay_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($barangay['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="complete_address" class="form-label required-field">Kumpletong Tirahan (Complete Address)</label>
                            <input type="text" class="form-control" id="complete_address" name="complete_address"
                                   value="<?= htmlspecialchars($request['complete_address']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Program Selection -->
                <div class="form-section">
                    <h5 class="mb-4">Assistance Program</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="program" class="form-label required-field">Program</label>
                            <select class="form-select" id="program" name="assistance_id" required>
                                <option value="" disabled>Select a program</option>
                                <?php foreach ($assistance_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" 
                                        data-requirement="<?= htmlspecialchars($type['specific_requirement']) ?>"
                                        <?= $type['id'] == $request['assistance_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Program Specific Requirements Section -->
                <div class="form-section" id="specificRequirementSection">
                    <h5 class="mb-4" id="specificRequirementTitle"><?= htmlspecialchars($request['specific_requirement']) ?></h5>
                    <div class="mb-3">
                        <div class="document-upload" onclick="document.getElementById('specificRequest').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload <span id="specificRequirementName"><?= htmlspecialchars($request['specific_requirement']) ?></span></p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="specificRequest" name="specific_request" accept="image/*,.pdf" style="display: none;">
                        <img id="specificRequestPreview" class="document-preview">
                        <div id="specificRequestError" class="text-danger small"></div>
                    </div>
                </div>
                <!-- Standard Requirements Section -->
                <div class="form-section">
                    <h5 class="mb-4">Mga Kinakailangang Dokumento (Required Documents)</h5>
                    
                    <!-- Brgy. Indigency Certificate -->
                    <div class="mb-4">
                        <label class="form-label required-field">Brgy. Indigency Certificate</label>
                        <div class="document-upload" onclick="document.getElementById('indigencyCert').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Brgy. Indigency Certificate</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="indigencyCert" name="indigency_cert" accept="image/*,.pdf" style="display: none;">
                        <img id="indigencyCertPreview" class="document-preview">
                        <div id="indigencyCertError" class="text-danger small"></div>
                    </div>
                    
                    <!-- Xerox ng ID -->
                    <div class="mb-4">
                        <label class="form-label required-field">Xerox ng ID na may address ng Pulilan</label>
                        <div class="document-upload" onclick="document.getElementById('idCopy').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Xerox ng ID</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="idCopy" name="id_copy" accept="image/*,.pdf" style="display: none;">
                        <img id="idCopyPreview" class="document-preview">
                        <div id="idCopyError" class="text-danger small"></div>
                    </div>
                    
                    <!-- Sulat Kahilingan -->
                    <div class="mb-4">
                        <label class="form-label required-field">Sulat kahilingan na nakapangalan kay Vice Mayor RJ Peralta</label>
                        <div class="document-upload" onclick="document.getElementById('requestLetter').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Sulat kahilingan</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="requestLetter" name="request_letter" accept="image/*,.pdf" style="display: none;">
                        <img id="requestLetterPreview" class="document-preview">
                        <div id="requestLetterError" class="text-danger small"></div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-save"></i> Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // update specific requirement when program changes
    $('#program').change(function() {
        const selectedOption = $(this).find('option:selected');
        const requirement = selectedOption.data('requirement');
        $('#specificRequirementTitle').text(requirement);
        $('#specificRequirementName').text(requirement);
    });

    // file upload preview handlers
    const setupFilePreview = (inputId, previewId, errorId) => {
        $(`#${inputId}`).change(function(e) {
            const file = this.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file) {
                if (file.size > maxSize) {
                    $(`#${errorId}`).text('File size exceeds 5MB limit');
                    $(`#${previewId}`).hide();
                    this.value = '';
                    return;
                }
                
                $(`#${errorId}`).text('');
                
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $(`#${previewId}`).attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(file);
                } else {
                    $(`#${previewId}`).hide();
                }
            }
        });
    };

    // setup all file previews
    setupFilePreview('specificRequest', 'specificRequestPreview', 'specificRequestError');
    setupFilePreview('indigencyCert', 'indigencyCertPreview', 'indigencyCertError');
    setupFilePreview('idCopy', 'idCopyPreview', 'idCopyError');
    setupFilePreview('requestLetter', 'requestLetterPreview', 'requestLetterError');

    // form submission handler with SweetAlert
    $('#editRequestForm').submit(function(e) {
        e.preventDefault();
        
        // clear previous errors
        $('.is-invalid').removeClass('is-invalid');
        $('.text-danger').text('');

        // validate required fields
        let isValid = true;
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            }
        });

        if (!isValid) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please fill all required fields',
                confirmButtonColor: '#4CAF50'
            });
            return;
        }

        // submit via AJAX 
        const formData = new FormData(this);
        const submitBtn = $('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Tagumpay!',
                    text: 'Matagumpay na na-update ang kahilingan',
                    confirmButtonColor: '#4CAF50',
                    willClose: () => {
                        window.location.href = 'status.php';
                    }
                });
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Request');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseText || 'An error occurred while updating the request',
                    confirmButtonColor: '#4CAF50'
                });
            }
        });
    });

    // initialize the requirement text on page load
    const initialRequirement = $('#program option:selected').data('requirement');
    $('#specificRequirementTitle').text(initialRequirement);
    $('#specificRequirementName').text(initialRequirement);
});
</script>

<?php include '../includes/footer.php'; ?>