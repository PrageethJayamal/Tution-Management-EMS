<?php
require_once 'config/db.php';

// If already logged in, redirect to their respective dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tuition Center Management</title>
    <link rel="icon" type="image" href="assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FAFAFA;
            --surface: #FFFFFF;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --accent: #6366F1;
            --accent-hover: #4F46E5;
            --border: #E5E7EB;
            --glow: rgba(99, 102, 241, 0.15);
        }
        
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg); 
            color: var(--text-primary); 
            line-height: 1.6; 
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 5%; }
        
        /* Navbar Minimal */
        nav { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 40px 0; 
        }
        .logo { 
            font-size: 20px; 
            font-weight: 700; 
            letter-spacing: -0.5px; 
            color: var(--text-primary);
        }
        .nav-links { display: flex; gap: 40px; align-items: center; }
        .nav-links a { 
            font-size: 14px; 
            font-weight: 500; 
            color: var(--text-secondary); 
            transition: color 0.2s; 
        }
        .nav-links a:hover { color: var(--text-primary); }
        .btn-login { 
            background: var(--surface); 
            color: var(--text-primary) !important; 
            padding: 10px 20px; 
            border: 1px solid var(--border);
            border-radius: 6px; 
            font-size: 14px;
            font-weight: 500; 
            transition: all 0.2s; 
        }
        .btn-login:hover { 
            border-color: var(--text-primary); 
        }

        /* Hero Minimal */
        .hero { 
            padding: 120px 0 100px; 
            text-align: center; 
            background: linear-gradient(180deg, #EEF2FF 0%, var(--bg) 100%);
        }
        .hero h1 { 
            font-size: 64px; 
            font-weight: 700; 
            margin: 0 0 24px; 
            color: var(--text-primary); 
            line-height: 1.1; 
            letter-spacing: -2px; 
        }
        .hero p { 
            font-size: 20px; 
            color: var(--text-secondary); 
            max-width: 540px; 
            margin: 0 auto 48px; 
            font-weight: 300;
        }
        .hero-btns { 
            display: flex; 
            justify-content: center; 
            gap: 16px; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, var(--accent) 0%, #8B5CF6 100%);
            color: #fff; 
            padding: 14px 28px; 
            border-radius: 6px; 
            font-size: 15px; 
            font-weight: 600; 
            transition: transform 0.2s, box-shadow 0.2s; 
            border: none;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 15px rgba(99, 102, 241, 0.35); 
        }
        
        .btn-outline { 
            background: transparent; 
            color: var(--text-primary); 
            padding: 14px 28px; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            font-size: 15px; 
            font-weight: 500; 
            transition: border-color 0.2s; 
        }
        .btn-outline:hover { border-color: var(--text-primary); }

        /* Portals Minimal */
        .portals { padding: 100px 0; background-color: var(--bg); }
        .portals-header { text-align: center; margin-bottom: 60px; }
        .portals-header h2 { font-size: 36px; font-weight: 700; color: var(--text-primary); margin: 0 0 15px; letter-spacing: -1px; }
        .portals-header p { font-size: 18px; color: var(--text-secondary); }
        .grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 40px; 
        }
        
        /* Interactive Cards with Spotlight */
        .portal-card, .feature-card { 
            background: var(--surface); 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            transition: all 0.2s; 
            position: relative;
            overflow: hidden;
            display: block;
            text-decoration: none;
        }
        
        /* Inside Content Layers */
        .portal-card > *, .feature-card > * {
            position: relative;
            z-index: 2;
        }
        
        /* The Magic Spotlight Glow */
        .portal-card::before, .feature-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            background: radial-gradient(
                400px circle at var(--mouse-x, 0) var(--mouse-y, 0), 
                var(--glow),
                transparent 40%
            );
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        
        .portal-card:hover::before, .feature-card:hover::before {
            opacity: 1; 
        }

        .portal-card {
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center;
            padding: 60px 40px; 
            cursor: pointer;
            height: 100%;
        }
        .portal-card:hover { 
            border-color: var(--text-primary); 
            transform: translateY(-2px); 
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.08); 
        }
        .portal-icon { font-size: 48px; margin-bottom: 24px; }
        .portal-card h3 { font-size: 22px; font-weight: 600; margin: 0 0 10px; color: var(--text-primary); }
        .portal-card p { color: var(--text-secondary); font-size: 16px; margin: 0; }

        /* Features Minimal */
        .features { padding: 100px 0; background-color: var(--surface); border-top: 1px solid var(--border); }
        .features .grid { gap: 60px 40px; }
        .feature-card { 
            text-align: left; 
            padding: 40px;
            background: transparent; 
            border: none;
        }
        .feature-card:hover {
            background: var(--bg);
        }
        .feature-card h3 { 
            font-size: 18px; 
            font-weight: 600; 
            margin: 0 0 12px; 
            color: var(--text-primary); 
        }
        .feature-card p { 
            color: var(--text-secondary); 
            font-size: 15px; 
            margin: 0;
            line-height: 1.6;
        }
        .feature-line {
            width: 24px;
            height: 2px;
            background-color: var(--accent);
            margin-bottom: 20px;
        }

        /* Footer Minimal */
        footer { 
            background: var(--surface); 
            color: var(--text-secondary); 
            padding: 40px 0; 
            text-align: center; 
            font-size: 13px; 
            border-top: 1px solid var(--border);
        }
        
        /* Fade In Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(25px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .fade-in.appear {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 992px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .hero h1 { font-size: 42px; letter-spacing: -1px; }
            .hero p { font-size: 18px; }
            nav { flex-direction: column; gap: 20px; }
            .nav-links { gap: 20px; flex-wrap: wrap; justify-content: center; }
        }
        @media (max-width: 600px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <nav>
        <div class="logo">TuitionCenter.</div>
        <div class="nav-links">
            <a href="#portals">Member Portals</a>
            <a href="#features">What we offer</a>
            <a href="center_login.php">Center Login</a>
            <a href="center_register.php" class="btn-login" style="background:var(--text-primary);color:#fff!important;border:none;">Register Center</a>
        </div>
    </nav>
</div>

<section class="hero">
    <div class="container">
        <h1 class="fade-in">Empower your Tuition Center.</h1>
        <p class="fade-in">A premium platform for institutes to track student attendance, coordinate faculty, and communicate with parents seamlessly.</p>
        <div class="hero-btns fade-in">
            <a href="center_register.php" class="btn-primary">Register Institute</a>
            <a href="center_login.php" class="btn-outline">Center Login</a>
        </div>
    </div>
</section>

<section id="portals" class="portals">
    <div class="container">
        <div class="portals-header fade-in">
            <h2>Access Your Portal</h2>
            <p>Select your role to securely log into your customized dashboard.</p>
        </div>
        <div class="grid">
            <a href="center_login.php" class="portal-card fade-in">
                <div class="portal-icon">🏢</div>
                <h3>Institute Login</h3>
                <p>Manage your tuition center</p>
            </a>
            <a href="login.php?role=faculty" class="portal-card fade-in" style="transition-delay: 100ms;">
                <div class="portal-icon">👩‍🏫</div>
                <h3>Faculty</h3>
                <p>Manage classes and grading</p>
            </a>
            <a href="login.php?role=student" class="portal-card fade-in" style="transition-delay: 200ms;">
                <div class="portal-icon">🎓</div>
                <h3>Student</h3>
                <p>View your academic progress</p>
            </a>
            <a href="login.php?role=parent" class="portal-card fade-in" style="transition-delay: 300ms;">
                <div class="portal-icon">👪</div>
                <h3>Parent</h3>
                <p>Monitor your child's performance</p>
            </a>
        </div>
    </div>
</section>

<section id="features" class="features">
    <div class="container">
        <div class="grid">
            <div class="feature-card fade-in">
                <div class="feature-line"></div>
                <h3>Faculty Coordination</h3>
                <p>Onboard educators and assign them to specific classes. A unified space to oversee teaching schedules and responsibilities.</p>
            </div>
            <div class="feature-card fade-in" style="transition-delay: 100ms;">
                <div class="feature-line"></div>
                <h3>Attendance Tracking</h3>
                <p>Teachers can log presence instantly. Students and parents receive transparent, real-time access to attendance histories.</p>
            </div>
            <div class="feature-card fade-in" style="transition-delay: 200ms;">
                <div class="feature-line"></div>
                <h3>Grade Reporting</h3>
                <p>Record exam marks and track academic progress methodically across terms. Secure digital report cards for every student.</p>
            </div>
            <div class="feature-card fade-in" style="transition-delay: 300ms;">
                <div class="feature-line"></div>
                <h3>Parent Access</h3>
                <p>Dedicated digital portals keep guardians informed. Seamlessly switch between multiple children to review metrics.</p>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        &copy; <?php echo date('Y'); ?> TuitionCenter. Minimalist educational software.
    </div>
</footer>

<script>
    // Spotlight Effect Script
    document.addEventListener('mousemove', e => {
        const cards = document.querySelectorAll('.portal-card, .feature-card');
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });

    // Fade-In Scroll Intersection Observer
    const faders = document.querySelectorAll('.fade-in');
    const appearOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px"
    };

    const appearOnScroll = new IntersectionObserver(function(entries, observer) {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                return;
            } else {
                entry.target.classList.add('appear');
                observer.unobserve(entry.target);
            }
        });
    }, appearOptions);

    faders.forEach(fader => {
        appearOnScroll.observe(fader);
    });
</script>

</body>
</html>
