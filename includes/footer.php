</main>
    </div>
    
    <footer class="bg-munici-green text-white py-4 mt-auto">
        <div class="container text-center">
            <p class="mb-1">Municipality of Pulilan, Bulacan, Philippines</p>
            <p class="mb-0">© <?= date('Y') ?> MuniciHelp - All Rights Reserved</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.logout-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Ready to Leave?',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--munici-green)',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Logout',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = link.getAttribute('href');
                    }
                });
            });
        });
    });
    </script>
    <?php if(isset($customScript)): ?>
        <script src="<?= $basePath ?>assets/js/<?= $customScript ?>"></script>
    <?php endif; ?>
</body>
</html>