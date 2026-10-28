<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In – PMS</title>
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            margin: 0;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            padding: 15px;
            position: relative;
            z-index: 10;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4f46e5 0%, #a855f7 100%);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 12px;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .logo-title {
            color: #ffffff;
            font-weight: 700;
            font-size: 24px;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }

        .logo-subtitle {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }

        .form-label {
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 500;
            margin-left: 2px;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-right: none;
            color: #94a3b8;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            font-size: 15px;
            padding: 12px 16px;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.06);
            border-color: #8b5cf6;
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        .form-control:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: #8b5cf6;
            color: #8b5cf6;
        }

        .btn-auth {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #d946ef 100%);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            padding: 13px 20px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 8px 16px rgba(139, 92, 246, 0.25);
            margin-top: 10px;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(139, 92, 246, 0.4);
            filter: brightness(1.1);
        }

        .btn-auth:active {
            transform: translateY(0);
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #94a3b8;
        }

        .auth-footer a {
            color: #c084fc;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .auth-footer a:hover {
            color: #e9d5ff;
            text-decoration: underline;
        }

        /* Ambient background glow points */
        .glow-1 {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(0,0,0,0) 70%);
            top: 10%;
            left: 15%;
            z-index: 1;
            pointer-events: none;
        }

        .glow-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(217, 70, 239, 0.1) 0%, rgba(0,0,0,0) 70%);
            bottom: 10%;
            right: 15%;
            z-index: 1;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="glow-1"></div>
    <div class="glow-2"></div>

    <div class="auth-container">
        
        <?php
        require_once __DIR__ . '/../../includes/functions.php';
        $flash = flashGet();
        if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 small text-center" role="alert" style="background: rgba(255, 255, 255, 0.08); color: #fff;">
                <?= e($flash['msg']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="auth-card">
            <div class="logo-wrap">
                <div class="logo-icon">
                    <i class="bi bi-buildings-fill"></i>
                </div>
                <div class="logo-title">BD VAULT</div>
                <div class="logo-subtitle">PROPOSAL MANAGEMENT SYSTEM</div>
            </div>

            <form action="index.php?page=login_process" method="POST">
                <div class="mb-4">
                    <label for="username" class="form-label mb-2">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required autocomplete="off">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label mb-2">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-auth mt-2">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="index.php?page=register">Create one here</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
