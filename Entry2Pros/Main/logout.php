<?php
session_start();
if (isset($_GET['action']) && $_GET['action'] === 'confirmed') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        body {
            font-family: 'Poppins', sans-serif !important;
            background-color: #f8fafc;
        }

        .swal-popup-custom {
            padding: 2.5rem 1rem !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important;
        }

        .swal-title-custom {
            font-size: 22px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }

        /* Button Logout - Merah Soft (TIdak Sakit Mata) */
        .swal-confirm-button-custom {
            background-color: #f87171 !important;
            /* Merah pastel/soft */
            color: white !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            padding: 12px 32px !important;
            border-radius: 8px !important;
            margin-left: 20px !important;
            /* Jarak lebih luas */
            transition: all 0.2s ease !important;
            border: none !important;
            cursor: pointer !important;
            /* Ni paling penting supaya keluar simbol tangan */
        }

        .swal-confirm-button-custom:hover {
            background-color: #ef4444 !important;
            /* Gelap sikit masa hover */
            transform: translateY(-2px);
        }

        /* Button Cancel */
        .swal-cancel-button-custom {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            padding: 12px 32px !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer !important;
            /* Tambah juga kat sini */
            transition: all 0.2s ease !important;
        }

        .swal-cancel-button-custom:hover {
            background-color: #e2e8f0 !important;
            color: #1e293b !important;
        }
    </style>
</head>

<body>

    <script>
        window.onload = function() {
            Swal.fire({
                title: 'Terminate Session?',
                text: "Are you sure you want to leave the system?",
                icon: 'warning',
                iconColor: '#dc2626',
                showCancelButton: true,
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                reverseButtons: true, // Letak Cancel kat kiri, Logout kat kanan
                allowOutsideClick: false,
                showClass: {
                    popup: 'animate__animated animate__zoomIn animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOut animate__faster'
                },
                customClass: {
                    popup: 'swal-popup-custom',
                    title: 'swal-title-custom',
                    confirmButton: 'swal-confirm-button-custom',
                    cancelButton: 'swal-cancel-button-custom'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php?action=confirmed';
                } else {
                    window.history.back();
                }
            });
        };
    </script>
</body>

</html>