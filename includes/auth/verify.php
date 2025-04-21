<?php
session_start();
include '../db.php';

if (!isset($_SESSION['otp_verification'])) {
    header("Location: login.php");
    exit();
}

$verification = $_SESSION['otp_verification'];
$phone = $verification['phone'];
$type = $verification['type']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = implode('', $_POST['otp']);

    if ($type === 'password_reset') {
        // handle password reset verification
        $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->bind_result($id, $otp_hash, $otp_expiry);
        $stmt->fetch();
        $stmt->close();

        if ($otp_hash && password_verify($otp, $otp_hash) && strtotime($otp_expiry) > time()) {
            // otp verified, redirect to password reset page
            $_SESSION['password_reset_user'] = $id;
            unset($_SESSION['otp_verification']);
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Hindi wasto o expired na ang OTP. Pakisubukan ulit.";
        }
    } else {
        if ($type === 'login') {
            $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, otp_hash, otp_expiry FROM users WHERE phone = ? AND is_verified = 0");
        }
        
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->bind_result($id, $otp_hash, $otp_expiry);
        $stmt->fetch();
        $stmt->close();

        if ($otp_hash && password_verify($otp, $otp_hash) && strtotime($otp_expiry) > time()) {
            if ($type === 'registration') {
                // mark account as verified
                $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $update->bind_param("i", $id);
                $update->execute();
                $update->close();
            }

            $_SESSION['user_id'] = $id;
            unset($_SESSION['otp_verification']);
            header("Location: ../../index.php");
            exit();
        } else {
            $error = "Hindi wasto o expired na ang OTP. Pakisubukan ulit.";
        }
    }
}

$pageTitle = 'Verify OTP';
$isAuthPage = true;
include '../../includes/header.php';
?>

<div class="auth-container">
    <div class="text-center mb-4">
        <img src="../../assets/images/logo-pulilan.png" alt="Pulilan Logo" height="80">
        <h2 class="mt-3">OTP Verification</h2>
        <p class="text-muted">
            <?php 
            if ($type === 'registration') {
                echo 'Kumpirmasyon ng pagrehistro para sa ';
            } elseif ($type === 'login') {
                echo 'Kumpirmasyon ng pag-login para sa ';
            } else {
                echo 'Kumpirmasyon ng pag-reset ng password para sa ';
            }
            ?>
            <strong><?= $phone ?></strong>
        </p>
        <div id="countdown" class="text-munici-green mb-3">05:00</div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST" id="verifyForm">
        <div class="mb-3 d-flex justify-content-center">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" class="form-control otp-input mx-1 text-center" 
                       maxlength="1" required autocomplete="off"
                       <?= $i === 0 ? 'autofocus' : '' ?>>
            <?php endfor; ?>
        </div>
        <button type="submit" class="btn btn-munici-green w-100">Verify</button>
    </form>
    
    <p class="text-center mt-3">Walang natanggap na code? <a href="#" id="resend-otp">Mag-resend ng OTP</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let timeLeft = 300;
    const countdown = setInterval(() => {
        timeLeft--;
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        document.getElementById('countdown').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.getElementById('countdown').textContent = "OTP Expired";
            document.getElementById('countdown').className = "text-danger mb-3";
        }
    }, 1000);

    // otp input auto focus
    const otpInputs = document.querySelectorAll('.otp-input');
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            if (input.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && index > 0 && input.value.length === 0) {
                otpInputs[index - 1].focus();
            }
        });
    });

    // Resend OTP functionality
    document.getElementById('resend-otp').addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            const response = await fetch('auth.php?action=resend_otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    phone: '<?= $phone ?>',
                    type: '<?= $type ?>'
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                const alert = document.createElement('div');
                alert.className = 'alert alert-success floating-alert';
                alert.textContent = 'New OTP sent successfully!';
                document.body.appendChild(alert);
                
                setTimeout(() => {
                    alert.remove();
                }, 3000);
                
                timeLeft = 300;
            } else {
                alert('Failed to resend OTP: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    });
});
</script>

<style>
.floating-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    animation: fadeInOut 3s ease-in-out;
}

@keyframes fadeInOut {
    0%, 100% { opacity: 0; transform: translateY(-20px); }
    10%, 90% { opacity: 1; transform: translateY(0); }
}
</style>

<?php include '../../includes/footer.php'; ?>