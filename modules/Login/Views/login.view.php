<?php
/**
 * Login View
 * File: login.view.php
 *
 * @var string|null $error Error message
 */

if (!defined('ABSPATH')) {
    exit;
}

$error = $error ?? null;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name') . ' - Login'); ?></title>
    <?php wp_head(); ?>
    <style>
        :root {
            --primary: #2c3e50;
            --accent: #3498db;
            --error: #e74c3c;
            --bg: #f5f6fa;
            --card-bg: #ffffff;
            --text: #2c3e50;
            --text-muted: #7f8c8d;
            --border: #dfe6e9;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 12px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --primary: #ecf0f1;
                --bg: #1a1a2e;
                --card-bg: #16213e;
                --text: #ecf0f1;
                --text-muted: #a0a0b0;
                --border: #2a2a4a;
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text);
            line-height: 1.6;
        }

        .login-container { width: 100%; max-width: 420px; }

        .login-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .login-header { text-align: center; margin-bottom: 32px; }
        .login-logo { width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 32px; font-weight: 700; }
        .login-header h1 { font-size: 28px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
        .login-header p { color: var(--text-muted); font-size: 15px; }

        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: shake 0.4s ease; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        .alert-error { background: #fdf2f2; color: var(--error); border: 1px solid #fecaca; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: var(--text); margin-bottom: 8px; }
        .form-input { width: 100%; padding: 14px 16px; border: 2px solid var(--border); border-radius: 8px; font-size: 16px; color: var(--text); background: var(--card-bg); transition: all 0.3s ease; }
        .form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15); }

        .form-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--accent); }
        .checkbox-wrapper label { font-size: 14px; color: var(--text); cursor: pointer; }
        .forgot-link { font-size: 14px; color: var(--accent); text-decoration: none; transition: all 0.3s ease; }
        .forgot-link:hover { color: #2980b9; text-decoration: underline; }

        .btn { width: 100%; padding: 14px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #2980b9); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        .login-footer { text-align: center; margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--border); }
        .login-footer p { font-size: 14px; color: var(--text-muted); }
        .login-footer a { color: var(--accent); text-decoration: none; font-weight: 500; transition: all 0.3s ease; }
        .login-footer a:hover { color: #2980b9; text-decoration: underline; }

        @media (max-width: 480px) { .login-card { padding: 24px; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"><?php echo esc_html(mb_substr(get_bloginfo('name'), 0, 1)); ?></div>
                <h1><?php echo esc_html__('Welcome Back', 'radykfw'); ?></h1>
                <p><?php echo esc_html__('Sign in to your account', 'radykfw'); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span><?php echo esc_html($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="" novalidate>
                <?php wp_nonce_field('radykfw_login', 'radykfw_login_nonce'); ?>

                <div class="form-group">
                    <label for="username" class="form-label"><?php echo esc_html__('Username or Email', 'radykfw'); ?></label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="<?php echo esc_attr__('Enter your username', 'radykfw'); ?>" value="<?php echo esc_attr($_POST['username'] ?? ''); ?>" autocomplete="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label"><?php echo esc_html__('Password', 'radykfw'); ?></label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="<?php echo esc_attr__('Enter your password', 'radykfw'); ?>" autocomplete="current-password" required>
                </div>

                <div class="form-row">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember"><?php echo esc_html__('Remember me', 'radykfw'); ?></label>
                    </div>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="forgot-link"><?php echo esc_html__('Forgot password?', 'radykfw'); ?></a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <?php echo esc_html__('Sign In', 'radykfw'); ?>
                </button>
            </form>

            <div class="login-footer">
                <p><?php echo esc_html__("Don't have an account?", 'radykfw'); ?> <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php echo esc_html__('Sign up', 'radykfw'); ?></a></p>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>