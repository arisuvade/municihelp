<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../includes/auth/login.php');
    exit;
}

$page_title = "VM RJ Assistance Request";
include '../includes/header.php';

// Gget assistance types and brgy
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
                <h1 class="mb-1">Vice Mayor RJ Peralta Assistance Request</h1>
                <p class="mb-0">Punan ang form upang humiling ng tulong.</p>
            </div>
            <button class="btn btn-light" onclick="location.href='status.php'">
                <i class="fas fa-list"></i> View Status
            </button>
        </div>
    </div>
</div>

<div class="container">
    <div class="request-form-card card">
        <div class="alert alert-warning mb-4">
            <strong>Paunawa:</strong> Ang aming sistema ay nakabatay lamang sa mga dokumentong inyong isinusumite. Hindi kami mananagot kung sakaling may hindi pagkakatugma sa inyong ipinasa online at sa physical na dokumento. Responsibilidad ng nagpapasa na tiyakin ang tamang impormasyon at pagkakapareho ng mga dokumento. Kung may mali o hindi tugmang dokumento, maaaring hindi kayo makatangap ng assistance. Salamat sa inyong pang-unawa.
        </div>
        
        <div class="card-header">
            Assistance Request Form
        </div>
        <div class="card-body">
            <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                <!-- personal info section -->
                <div class="form-section">
                    <h5 class="mb-4">Personal na impormasyon ng nangangailangan</h5>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label required-field">Unang Pangalan (First Name)</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label">Gitnang Pangalan (Middle Name)</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label required-field">Apelyido (Last Name)</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="barangay" class="form-label required-field">Barangay</label>
                            <select class="form-select" id="barangay" name="barangay_id" required>
                                <option value="" selected disabled>Select barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?= $barangay['id'] ?>"><?= htmlspecialchars($barangay['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="complete_address" class="form-label required-field">Kumpletong Tirahan (Complete Address)</label>
                            <input type="text" class="form-control" id="complete_address" name="complete_address" required>
                        </div>
                    </div>
                </div>

                <!-- program selection -->
                <div class="form-section">
                    <h5 class="mb-4">Assistance Program</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="program" class="form-label required-field">Program</label>
                            <select class="form-select" id="program" name="assistance_id" required>
                                <option value="" selected disabled>Select a program</option>
                                <?php foreach ($assistance_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-requirement="<?= htmlspecialchars($type['specific_requirement']) ?>">
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- program specific req section -->
                <div class="form-section" id="specificRequirementSection" style="display: none;">
                    <h5 class="mb-4" id="specificRequirementTitle">Specific Requirement</h5>
                    <div class="mb-3">
                        <div class="document-upload" onclick="document.getElementById('specificRequest').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload <span id="specificRequirementName"></span></p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="specificRequest" name="specific_request" accept="image/*,.pdf" style="display: none;" required>
                        <img id="specificRequestPreview" class="document-preview">
                        <div id="specificRequestError" class="text-danger small"></div>
                    </div>
                </div>

                <!-- standard requirements section -->
                <div class="form-section">
                    <h5 class="mb-4">Mga Kinakailangang Dokumento (Required Documents)</h5>
                    
                    <!-- brgy. indigency -->
                    <div class="mb-4">
                        <label class="form-label required-field">Brgy. Indigency Certificate</label>
                        <div class="document-upload" onclick="document.getElementById('indigencyCert').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Brgy. Indigency Certificate</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="indigencyCert" name="indigency_cert" accept="image/*,.pdf" style="display: none;" required>
                        <img id="indigencyCertPreview" class="document-preview">
                        <div id="indigencyCertError" class="text-danger small"></div>
                    </div>
                    
                    <!-- xerox ng id -->
                    <div class="mb-4">
                        <label class="form-label required-field">Xerox ng ID na may address ng Pulilan</label>
                        <div class="document-upload" onclick="document.getElementById('idCopy').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Xerox ng ID</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="idCopy" name="id_copy" accept="image/*,.pdf" style="display: none;" required>
                        <img id="idCopyPreview" class="document-preview">
                        <div id="idCopyError" class="text-danger small"></div>
                    </div>
                    
                    <!-- sulat kahilingan -->
                    <div class="mb-4">
                        <label class="form-label required-field">Sulat kahilingan na nakapangalan kay Vice Mayor RJ Peralta</label>
                        <div class="document-upload" onclick="document.getElementById('requestLetter').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p class="mb-1">Click to upload Sulat kahilingan</p>
                            <p class="small text-muted">JPG, PNG, or PDF (Max: 5MB)</p>
                        </div>
                        <input type="file" id="requestLetter" name="request_letter" accept="image/*,.pdf" style="display: none;" required>
                        <img id="requestLetterPreview" class="document-preview">
                        <div id="requestLetterError" class="text-danger small"></div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- success modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                </div>
                <h4 class="mb-3">Request Submitted Successfully!</h4>
                <p class="mb-4">Natanggap na ang iyong kahilingan. Aabisuhan ka tungkol sa status ng iyong aplikasyon.</p>
                <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="location.href='status.php'">
                    View Status
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // program selection change
    $('#program').change(function() {
        const selectedOption = $(this).find('option:selected');
        const requirement = selectedOption.data('requirement');
        
        if (selectedOption.val()) {
            $('#specificRequirementSection').show();
            $('#specificRequirementName').text(requirement);
            $('#specificRequirementTitle').text(requirement);
            $('#specificRequest').prop('required', true);
        } else {
            $('#specificRequirementSection').hide();
            $('#specificRequest').prop('required', false);
        }
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

    // form submission handler
    $('#requestForm').submit(async function(e) {
        e.preventDefault();
        // vlear previous errors
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
            Swal.fire('Error', 'Please fill all required fields', 'error');
            return;
        }

        // validate file sizes
        let filesValid = true;
        $('input[type="file"][required]').each(function() {
            if (!this.files || this.files.length === 0) {
                filesValid = false;
                $(this).addClass('is-invalid');
            } else if (this.files[0].size > 5 * 1024 * 1024) {
                filesValid = false;
                $(this).addClass('is-invalid');
                $(`#${this.id}Error`).text('File size exceeds 5MB limit');
            }
        });

        if (!filesValid) {
            Swal.fire('Error', 'Please check your file uploads', 'error');
            return;
        }

        // prepare form data
        const formData = new FormData(this);
        formData.append('user_id', <?= $_SESSION['user_id'] ?? 'null' ?>);

        // disable submit button
        const submitBtn = $('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        try {
            // first check for existing request
            const checkResponse = await checkExistingRequest(formData);

            if (checkResponse.exists) {
                Swal.fire({
                    title: 'May Umiral Nang Kahilingan',
                    text: 'Mayroon ka nang nakabinbing kahilingan para sa ganitong uri ng tulong sa buwang ito.',
                    icon: 'warning'
                });
                return;
            }

            // if no existing request, submit the form
            const submitResponse = await submitRequest(formData);

            if (submitResponse.success) {
                $('#successModal').modal('show');
            } else {
                Swal.fire('Error', submitResponse.message || 'Submission failed', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred. Please try again.', 'error');
        } finally {
            submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
        }
    });

    // check existing request
    async function checkExistingRequest(formData) {
        const checkData = {
            user_id: formData.get('user_id'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            barangay_id: formData.get('barangay_id'),
            assistance_id: formData.get('assistance_id')
        };

        const response = await $.ajax({
            url: 'check_existing_request.php',
            type: 'POST',
            data: checkData,
            dataType: 'json'
        });

        return response;
    }

    // submit the request
    async function submitRequest(formData) {
        const response = await $.ajax({
            url: 'submit_request.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        });

        return response;
    }
});
</script>

<?php include '../includes/footer.php'; ?>