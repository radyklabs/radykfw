<?php
/**
 * Welcome Index View
 * File: index.view.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome | <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        :root {
            --primary: #1a1a2e;
            --accent: #0f3460;
            --highlight: #e94560;
            --text: #eaeaea;
            --text-muted: #a0a0b0;
            --bg: #0f0f1a;
            --card-bg: #1a1a2e;
            --border: #2a2a4a;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --radius: 16px;
            --shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        @media (prefers-color-scheme: light) {
            :root {
                --primary: #ffffff;
                --accent: #eef2ff;
                --highlight: #e94560;
                --text: #1a1a2e;
                --text-muted: #64748b;
                --bg: #fafafa;
                --card-bg: #ffffff;
                --border: #e2e8f0;
                --shadow: 0 10px 40px rgba(0,0,0,0.08);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; overflow-x: hidden; }

        body::before {
            content: '';
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.15) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(245, 87, 108, 0.15) 0%, transparent 50%);
            pointer-events: none; z-index: -1;
        }

        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 24px; }

        .hero { min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 80px 24px; position: relative; }
        .hero-content { max-width: 800px; animation: fadeInUp 0.8s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--gradient); border-radius: 50px; font-size: 14px; font-weight: 600; color: white; margin-bottom: 32px; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3); }
        .hero-badge::before { content: ''; width: 8px; height: 8px; background: var(--highlight); border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }

        .hero h1 { font-size: clamp(36px, 6vw, 64px); font-weight: 800; line-height: 1.1; margin-bottom: 24px; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero p { font-size: clamp(18px, 2.5vw, 24px); color: var(--text-muted); margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto; }

        .hero-cta { display: inline-flex; align-items: center; gap: 12px; padding: 18px 40px; background: var(--gradient); border-radius: 50px; color: white; font-size: 18px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4); }
        .hero-cta:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5); }

        .features { padding: 80px 0; }
        .section-header { text-align: center; margin-bottom: 60px; }
        .section-tag { display: inline-block; padding: 8px 16px; background: var(--accent); color: var(--highlight); border-radius: 50px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .section-header h2 { font-size: clamp(28px, 4vw, 42px); font-weight: 700; margin-bottom: 16px; }
        .section-header p { font-size: 18px; color: var(--text-muted); max-width: 600px; margin: 0 auto; }

        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .feature-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 40px 32px; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gradient); transform: scaleX(0); transform-origin: left; transition: transform 0.4s ease; }
        .feature-card:hover { transform: translateY(-8px); border-color: var(--accent); box-shadow: var(--shadow); }
        .feature-card:hover::before { transform: scaleX(1); }

        .feature-icon { width: 56px; height: 56px; border-radius: 14px; background: var(--gradient); display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
        .feature-icon svg { width: 28px; height: 28px; stroke: white; }
        .feature-card h3 { font-size: 20px; font-weight: 600; margin-bottom: 12px; }
        .feature-card p { color: var(--text-muted); font-size: 15px; line-height: 1.7; }

        .cta-section { padding: 100px 0; text-align: center; }
        .cta-card { background: var(--gradient); border-radius: var(--radius); padding: 80px 40px; position: relative; overflow: hidden; }
        .cta-content { position: relative; z-index: 1; }
        .cta-card h2 { font-size: clamp(28px, 4vw, 42px); font-weight: 700; color: white; margin-bottom: 16px; }
        .cta-card p { font-size: 18px; color: rgba(255,255,255,0.9); margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .cta-button { display: inline-flex; align-items: center; gap: 12px; padding: 18px 40px; background: white; color: #667eea; border-radius: 50px; font-size: 18px; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        .cta-button:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }

        .footer { padding: 60px 0 40px; border-top: 1px solid var(--border); text-align: center; }
        .footer-links { display: flex; justify-content: center; gap: 32px; margin-bottom: 24px; flex-wrap: wrap; }
        .footer-links a { color: var(--text-muted); text-decoration: none; font-size: 14px; transition: all 0.3s ease; }
        .footer-links a:hover { color: var(--accent); }
        .footer-copyright { font-size: 13px; color: var(--text-muted); }

        @media (max-width: 768px) { .features-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <section class="hero" aria-labelledby="hero-title">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">RadykFW HMVC Framework</div>
                <h1 id="hero-title">Welcome to RadykFW</h1>
                <p>A powerful Hierarchical MVC framework for WordPress with custom routing</p>
                <a href="#features" class="hero-cta">Explore Features</a>
            </div>
        </div>
    </section>

    <section class="features" id="features" aria-labelledby="features-title">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Features</span>
                <h2 id="features-title">Everything you need to build</h2>
                <p>Powerful features for modern WordPress development</p>
            </div>

            <div class="features-grid">
                <article class="feature-card">
                    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg></div>
                    <h3>HMVC Architecture</h3>
                    <p>Hierarchical Model-View-Controller for scalable, maintainable code organization.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6M1 12h6m6 0h6"></path></svg></div>
                    <h3>Custom Routing</h3>
                    <p>Intercept and handle custom routes before WordPress native routing.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.37 3.13a2.12 2.12 0 0 1 3 3l-7 7a2.12 2.12 0 0 1-3 0l-7-7a2.12 2.12 0 0 1 0-3l7-7a2.12 2.12 0 0 1 3 0z"></path></svg></div>
                    <h3>Modular Design</h3>
                    <p>Self-contained modules with their own controllers, models, and views.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 19 22 12 13 5 13 19"></polygon><polyline points="2 22 13 19 2 16"></polyline></svg></div>
                    <h3>High Performance</h3>
                    <p>Lightweight core with intelligent caching for minimal overhead.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="cta-section" aria-labelledby="cta-title">
        <div class="container">
            <div class="cta-card">
                <div class="cta-content">
                    <h2 id="cta-title">Ready to start building?</h2>
                    <p>Join developers using RadykFW for their WordPress projects.</p>
                    <a href="#" class="cta-button">Get Started</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer" role="contentinfo">
        <div class="container">
            <div class="footer-links">
                <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
                <a href="<?php echo esc_url(home_url('/documentation')); ?>">Documentation</a>
                <a href="https://github.com/radyklabs/radykfw" target="_blank" rel="noopener">GitHub</a>
                <a href="<?php echo esc_url(home_url('/changelog')); ?>">Changelog</a>
            </div>
            <p class="footer-copyright">&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Built with RadykFW</p>
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>
</html>