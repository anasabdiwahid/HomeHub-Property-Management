<?php
/**
 * Global Footer Include
 * HomeHub Property Management System
 */

declare(strict_types=1);
?>
    <!-- Floating WhatsApp Action Button (Icon Only) -->
    <a href="https://wa.me/252615554321?text=Hello%20HomeHub,%20I%20am%20interested%20in%20your%20property%20services" 
       target="_blank" 
       rel="noopener noreferrer" 
       class="floating-whatsapp-btn" 
       id="floatingWhatsappBtn"
       title="Chat with us on WhatsApp"
       aria-label="Chat with us on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>

    <!-- Bootstrap 5 Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <!-- Chart.js for Dashboards & Reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- SweetAlert2 (In-Page Modern Dialogs & Alerts) -->
    <script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

    <!-- Custom Main JS -->
    <script src="<?= BASE_URL; ?>assets/js/main.js?v=<?= file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time(); ?>"></script>
</body>
</html>
