<?php
session_start();

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function clean_header($value)
{
    return trim(str_replace(["\r", "\n"], '', (string) $value));
}

function build_captcha()
{
    $a = random_int(2, 9);
    $b = random_int(2, 9);

    return [
        'question' => "What is {$a} + {$b}?",
        'answer' => $a + $b,
    ];
}

function new_form_token()
{
    return bin2hex(random_bytes(16));
}

$siteName = 'PT. Unico Tractors Indonesia';
$recipientEmail = 'info@unicotractors.com';
$feedbackMessage = '';
$feedbackType = '';
$fieldErrors = [];
$values = [
    'name' => '',
    'email' => '',
    'company' => '',
    'product' => '',
    'message' => '',
];

if (isset($_GET['refresh_captcha'])) {
    $_SESSION['contact_captcha'] = build_captcha();
    $_SESSION['contact_form_token'] = new_form_token();
    $_SESSION['contact_form_started_at'] = time();
}

if (!isset($_SESSION['contact_captcha'])) {
    $_SESSION['contact_captcha'] = build_captcha();
}

if (!isset($_SESSION['contact_form_token'])) {
    $_SESSION['contact_form_token'] = new_form_token();
}

if (!isset($_SESSION['contact_form_started_at'])) {
    $_SESSION['contact_form_started_at'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = clean_input($_POST['name'] ?? '');
    $values['email'] = clean_input($_POST['email'] ?? '');
    $values['company'] = clean_input($_POST['company'] ?? '');
    $values['product'] = clean_input($_POST['product'] ?? '');
    $values['message'] = clean_input($_POST['message'] ?? '');

    $honeypot = clean_input($_POST['website'] ?? '');
    $captchaAnswer = clean_input($_POST['captcha_answer'] ?? '');
    $postedToken = (string) ($_POST['form_token'] ?? '');
    $postedStartedAt = (int) ($_POST['form_started_at'] ?? 0);

    if (!hash_equals($_SESSION['contact_form_token'], $postedToken)) {
        $fieldErrors['form'] = 'Invalid submission token. Please reload the page and try again.';
    }

    if ($honeypot !== '') {
        $fieldErrors['form'] = 'Submission blocked.';
    }

    if (time() - $postedStartedAt < 3) {
        $fieldErrors['form'] = 'Please take a moment before submitting the form.';
    }

    if (mb_strlen($values['name']) < 2) {
        $fieldErrors['name'] = 'Name must contain at least 2 characters.';
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }

    if (mb_strlen($values['message']) < 20) {
        $fieldErrors['message'] = 'Message must be at least 20 characters long.';
    }

    if ($values['company'] !== '' && mb_strlen($values['company']) > 120) {
        $fieldErrors['company'] = 'Company name is too long.';
    }

    if ($values['product'] !== '' && mb_strlen($values['product']) > 120) {
        $fieldErrors['product'] = 'Product text is too long.';
    }

    if (!ctype_digit($captchaAnswer)) {
        $fieldErrors['captcha'] = 'Captcha answer must be numeric.';
    } elseif ((int) $captchaAnswer !== (int) $_SESSION['contact_captcha']['answer']) {
        $fieldErrors['captcha'] = 'Captcha answer is incorrect.';
    }

    if (empty($fieldErrors)) {
        $subject = '[' . $siteName . '] New Contact Message';
        $messageBody = "New contact form submission\n\n";
        $messageBody .= 'Name: ' . $values['name'] . "\n";
        $messageBody .= 'Email: ' . $values['email'] . "\n";
        $messageBody .= 'Company: ' . ($values['company'] !== '' ? $values['company'] : '-') . "\n";
        $messageBody .= 'Product: ' . ($values['product'] !== '' ? $values['product'] : '-') . "\n";
        $messageBody .= "Message:\n" . $values['message'] . "\n";

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . $siteName . ' <' . $recipientEmail . '>';
        $headers[] = 'Reply-To: ' . clean_header($values['email']);
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $mailSent = mail(
            $recipientEmail,
            $subject,
            $messageBody,
            implode("\r\n", $headers),
            '-f' . $recipientEmail
        );

        if ($mailSent) {
            $_SESSION['contact_flash'] = [
                'type' => 'is-success',
                'message' => 'Message sent successfully.',
            ];

            $_SESSION['contact_captcha'] = build_captcha();
            $_SESSION['contact_form_token'] = new_form_token();
            $_SESSION['contact_form_started_at'] = time();

            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#contact');
            exit;
        }

        $fieldErrors['form'] = 'Failed to send message. Please try again later.';
    }
}

if (isset($_SESSION['contact_flash'])) {
    $feedbackMessage = $_SESSION['contact_flash']['message'];
    $feedbackType = $_SESSION['contact_flash']['type'];
    unset($_SESSION['contact_flash']);
} elseif (!empty($fieldErrors)) {
    $feedbackMessage = implode(' ', $fieldErrors);
    $feedbackType = 'is-error';
}

$captchaQuestion = $_SESSION['contact_captcha']['question'];
$formToken = $_SESSION['contact_form_token'];
$formStartedAt = $_SESSION['contact_form_started_at'];
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PT. Unico Tractors Indonesia</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --gold: #d4960a;
            --gold-light: #f2b81c;
            --gold-pale: #fdf3d7;
            --navy: #0f1e3c;
            --navy-mid: #1a2f55;
            --navy-light: #2b4070;
            --white: #fafaf8;
            --gray: #8a8f9e;
            --gray-light: #f0efe9;
            --text: #1a1a2e;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--white);
            color: var(--text);
            overflow-x: hidden;
        }

        /* ── NAV ── */
        /* NAVBAR BASE */
        .navbar {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 1200px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0.8rem 1.5rem;

            background: rgba(15, 30, 60, 0.75);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.08);

            border-radius: 14px;
            z-index: 999;

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        /* LOGO */
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
        }

        .nav-logo img {
            width: 38px;
            height: auto;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .logo-text strong {
            font-size: 0.95rem;
            color: #fff;
            letter-spacing: 0.08em;
        }

        .logo-text span {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* NAV LINKS */
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            position: relative;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.65);
            text-decoration: none;
            transition: 0.3s ease;
        }

        /* underline modern */
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0%;
            height: 2px;
            background: var(--gold);
            transition: 0.3s;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* CTA BUTTON */
        .nav-cta {
            background: linear-gradient(135deg, var(--gold), #ffcc33);
            color: #0f1e3c;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;

            padding: 0.6rem 1.4rem;
            border-radius: 999px;
            text-decoration: none;

            box-shadow: 0 4px 15px rgba(212, 150, 10, 0.4);
            transition: all 0.25s ease;
        }

        .nav-cta:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 20px rgba(212, 150, 10, 0.6);
        }

        /* ── HERO ── */
        #hero {
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 30%, rgba(212, 150, 10, 0.08), transparent 40%),
                var(--navy);
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            overflow: hidden;
        }

        /* BACKGROUND GRID */
        .hero-bg-pattern {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* LEFT SIDE */
        .hero-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 9rem 5% 5rem 7%;
            position: relative;
            z-index: 2;
        }

        /* TAG */
        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 1.5rem;
        }

        .hero-tag::before {
            content: '';
            width: 30px;
            height: 2px;
            background: linear-gradient(to right, var(--gold), transparent);
        }

        /* TITLE */
        .hero-title {
            font-size: clamp(3rem, 5vw, 4.8rem);
            font-weight: 900;
            line-height: 1.05;
            color: #fff;
            margin-bottom: 1.5rem;
        }

        .hero-title span {
            display: block;
            background: linear-gradient(135deg, var(--gold-light), #ffd86b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* DESCRIPTION */
        .hero-desc {
            font-size: 1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.65);
            max-width: 460px;
            margin-bottom: 2.5rem;
        }

        /* BUTTONS */
        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gold), #ffcc33);
            color: var(--navy);
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 1rem 2.2rem;
            border-radius: 999px;
            text-decoration: none;

            box-shadow: 0 6px 20px rgba(212, 150, 10, 0.4);
            transition: all 0.25s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 10px 30px rgba(212, 150, 10, 0.6);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 1rem 2.2rem;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.2);

            backdrop-filter: blur(10px);
            transition: all 0.25s ease;
        }

        .btn-outline:hover {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(212, 150, 10, 0.1);
        }

        /* STATS */
        .hero-stats {
            display: flex;
            gap: 2.5rem;
            margin-top: 3.5rem;
        }

        .hero-stat-num {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--gold-light);
        }

        .hero-stat-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* RIGHT SIDE IMAGE */
        .hero-right {
            position: relative;
            overflow: hidden;
        }

        .hero-right::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, var(--navy) 5%, transparent 50%);
        }

        .hero-right img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.5) contrast(1.1) saturate(0.9);
            transform: scale(1.05);
        }

        /* FLOATING CARD */
        .hero-card {
            position: absolute;
            bottom: 3rem;
            right: 2rem;
            z-index: 3;

            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.15);

            padding: 1.5rem 2rem;
            border-radius: 14px;

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .hero-card-icon {
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
        }

        .hero-card-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
        }

        /* MOBILE */
        @media (max-width: 768px) {
            #hero {
                grid-template-columns: 1fr;
            }

            .hero-left {
                padding: 6rem 1.5rem 3rem;
                text-align: center;
                align-items: center;
            }

            .hero-right {
                height: 300px;
            }

            .hero-actions {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        /* ABOUT SECTION */
        #about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 85vh;
            background: #ffffff;
        }

        /* LEFT IMAGE */
        .about-visual {
            position: relative;
            overflow: hidden;
        }

        .about-visual img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.6) contrast(1.1);
            transform: scale(1.05);
        }

        /* OVERLAY */
        .about-visual-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg,
                    rgba(15, 30, 60, 0.85) 20%,
                    rgba(212, 150, 10, 0.25) 100%);
            display: flex;
            align-items: flex-end;
            padding: 3rem;
        }

        /* BADGE */
        .about-badge {
            background: linear-gradient(135deg, var(--gold), #ffcc33);
            padding: 1.2rem 1.6rem;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .about-badge-num {
            font-size: 2.6rem;
            font-weight: 900;
            color: var(--navy);
        }

        .about-badge-text {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--navy);
        }

        /* RIGHT CONTENT */
        .about-content {
            padding: 6rem 7% 6rem 5%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* TAG */
        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 1.2rem;
        }

        .section-tag::before {
            content: '';
            width: 25px;
            height: 2px;
            background: linear-gradient(to right, var(--gold), transparent);
        }

        /* TITLE */
        .section-title {
            font-size: clamp(2.2rem, 3vw, 3.2rem);
            font-weight: 900;
            line-height: 1.1;
            color: var(--navy);
            margin-bottom: 1.5rem;
        }

        /* TEXT */
        .about-content p {
            font-size: 1rem;
            line-height: 1.8;
            color: #4a5568;
            margin-bottom: 1rem;
        }

        /* PILLARS */
        .about-pillars {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
            margin-top: 2rem;
        }

        .pillar {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1.2rem;

            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.25s ease;
        }

        .pillar:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-color: rgba(212, 150, 10, 0.4);
        }

        .pillar-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--navy);
            margin-bottom: 0.4rem;
        }

        .pillar-text {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* MOBILE */
        @media (max-width: 768px) {
            #about {
                grid-template-columns: 1fr;
            }

            .about-content {
                padding: 3rem 1.5rem;
                text-align: center;
            }

            .about-pillars {
                grid-template-columns: 1fr;
            }

            .about-visual {
                height: 300px;
            }
        }

        /* ── VISION MISSION ── */
        #vision-mission {
            background: var(--navy);
            padding: 6rem 7%;
            position: relative;
            overflow: hidden;
        }

        /* LIGHT EFFECT */
        #vision-mission::before {
            content: '';
            position: absolute;
            right: -150px;
            top: -150px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 150, 10, 0.15), transparent 70%);
        }

        /* HEADER */
        .vm-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .vm-header .section-title {
            color: #fff;
        }

        /* GRID */
        .vm-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* VISION CARD */
        .vm-vision {
            background: linear-gradient(135deg, var(--gold), #ffcc33);
            border-radius: 18px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .vm-label {
            font-size: 0.7rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            font-weight: 700;
            opacity: 0.7;
            margin-bottom: 1.2rem;
        }

        .vm-text {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.4;
            color: var(--navy);
        }

        .vm-big {
            position: absolute;
            bottom: -10px;
            right: 15px;
            font-size: 7rem;
            font-weight: 900;
            color: rgba(15, 30, 60, 0.1);
        }

        /* MISSION CARD */
        .vm-mission {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* MISSION ITEM */
        .mission-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 1.5rem;

            display: flex;
            gap: 1.2rem;

            transition: all 0.25s ease;
        }

        .mission-item:hover {
            background: rgba(212, 150, 10, 0.12);
            border-color: rgba(212, 150, 10, 0.4);
            transform: translateY(-4px);
        }

        /* NUMBER */
        .mission-num {
            background: linear-gradient(135deg, var(--gold), #ffcc33);
            color: var(--navy);
            font-weight: 800;
            font-size: 0.9rem;

            width: 40px;
            height: 40px;
            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;
        }

        /* TEXT */
        .mission-text {
            font-size: 0.9rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.75);
        }

        /* MOBILE */
        @media (max-width: 768px) {
            .vm-grid {
                grid-template-columns: 1fr;
            }

            .vm-vision {
                text-align: center;
            }

            .vm-big {
                display: none;
            }
        }

        /* ── PRODUCTS ── */
        #products {
            padding: 7rem 7%;
            background: #f9fafb;
        }

        .products-header {
            margin-bottom: 3.5rem;
        }

        .products-intro {
            font-size: 1rem;
            line-height: 1.7;
            color: #6b7280;
            max-width: 600px;
            margin-top: 1rem;
        }

        /* GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.8rem;
        }

        /* CARD */
        .product-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);

            transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
        }

        /* HOVER */
        .product-card:hover {
            transform: translateY(-10px);

            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(212, 150, 10, 0.2),
                0 0 25px rgba(212, 150, 10, 0.15);
        }

        /* GLOW */
        .product-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right,
                    rgba(212, 150, 10, 0.25),
                    transparent 60%);
            opacity: 0;
            transition: 0.4s;
        }

        .product-card:hover::after {
            opacity: 1;
        }

        /* IMAGE */
        .product-image {
            aspect-ratio: 4 / 3;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover img {
            transform: scale(1.08);
        }

        /* CONTENT */
        .product-content {
            padding: 1.6rem;
        }

        .product-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--navy);
            margin-bottom: 1rem;
        }

        /* LIST */
        .product-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .product-list li {
            font-size: 0.85rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .product-list li::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--gold);
            border-radius: 50%;
        }

        /* ── INDUSTRY ── */
        /* SECTION */
        #industries {
            background: #f3f4f6;
            padding: 7rem 7%;
            max-width: 1400px;
            margin: auto;
        }

        /* HEADER */
        .industries-header {
            margin-bottom: 3.5rem;
        }

        .industries-desc {
            font-size: 1rem;
            line-height: 1.75;
            color: #6b7280;
            max-width: 700px;
            margin-top: 1rem;
        }

        /* GRID (5 SEBARIS) */
        .industries-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.4rem;
        }

        /* CARD */
        .industry-card {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            aspect-ratio: 3/4;
            cursor: pointer;

            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* HOVER EFFECT */
        .industry-card:hover {
            transform: translateY(-10px) scale(1.02);

            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.25),
                0 0 25px rgba(212, 150, 10, 0.15);
        }

        /* IMAGE */
        .industry-bg {
            position: absolute;
            inset: 0;
        }

        .industry-bg img {
            width: 100%;
            height: 100%;
            object-fit: cover;

            filter: brightness(0.65) saturate(0.9);
            transition: all 0.6s ease;
        }

        /* IMAGE HOVER */
        .industry-card:hover img {
            transform: scale(1.1);
            filter: brightness(0.75) saturate(1.1);
        }

        /* OVERLAY */
        .industry-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            padding: 1.4rem;

            background: linear-gradient(to top,
                    rgba(15, 30, 60, 0.95) 0%,
                    rgba(15, 30, 60, 0.5) 40%,
                    transparent 70%);
        }

        /* TEXT */
        .industry-name {
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            letter-spacing: 0.3px;
        }

        /* ACCENT LINE */
        .industry-accent {
            width: 28px;
            height: 3px;
            background: var(--gold);
            margin-bottom: 0.6rem;
            border-radius: 2px;
        }

        /* GLOW EFFECT */
        .industry-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top,
                    rgba(212, 150, 10, 0.2),
                    transparent 60%);
            opacity: 0;
            transition: 0.4s;
        }

        .industry-card:hover::after {
            opacity: 1;
        }

        /* Laptop */
        @media (max-width: 1200px) {
            .industries-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Tablet */
        @media (max-width: 768px) {
            .industries-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .industries-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── CONTACT ── */
        /* ========================= */
        /* CONTACT SECTION */
        /* ========================= */
        #contact {
            background: var(--navy);
            padding: 7rem 7%;
            position: relative;
            overflow: hidden;
        }

        /* GLOW BACKGROUND */
        #contact::after {
            content: '';
            position: absolute;
            left: -200px;
            bottom: -200px;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 150, 10, 0.12) 0%, transparent 60%);
            pointer-events: none;
        }

        /* LAYOUT */
        .contact-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5rem;
            max-width: 1100px;
            margin: 0 auto;
            align-items: center;
        }

        /* TEXT */
        .contact-tagline {
            font-family: 'Inter', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 1.5rem;
        }

        .contact-tagline span {
            color: var(--gold-light);
        }

        .contact-desc {
            font-size: 0.95rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 2rem;
        }

        /* INFO LIST */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 1.4rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
        }

        /* ICON */
        .contact-icon {
            width: 44px;
            height: 44px;
            border-radius: 6px;
            background: rgba(212, 150, 10, 0.12);
            border: 1px solid rgba(212, 150, 10, 0.25);

            display: flex;
            align-items: center;
            justify-content: center;

            color: var(--gold);
            font-size: 1rem;

            transition: all 0.3s ease;
        }

        .contact-item:hover .contact-icon {
            background: var(--gold);
            color: var(--navy);
            transform: translateY(-4px);
        }

        /* TEXT INFO */
        .contact-item-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--gold);
            font-weight: 600;
        }

        .contact-item-value {
            font-size: 0.9rem;
            color: #fff;
        }

        /* FORM */
        .contact-form {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 2.5rem;
            backdrop-filter: blur(8px);
        }

        .contact-form h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.5rem;
        }

        /* INPUT */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 5px;
            padding: 0.85rem 1rem;
            color: #fff;
            font-size: 0.9rem;
            outline: none;
            transition: 0.25s;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
        }

        .form-group input.is-invalid,
        .form-group textarea.is-invalid {
            border-color: #ff8b8b;
            box-shadow: 0 0 0 1px rgba(255, 139, 139, 0.18);
        }

        .form-group input.is-valid,
        .form-group textarea.is-valid {
            border-color: rgba(155, 231, 176, 0.45);
        }

        .form-feedback {
            margin-bottom: 1rem;
            min-height: 1.2rem;
            font-size: 0.85rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.7);
        }

        .form-feedback.is-error {
            color: #ffb4b4;
        }

        .form-feedback.is-success {
            color: #9be7b0;
        }

        .captcha-box {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
        }

        .captcha-label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--gold);
            font-weight: 700;
            margin-bottom: 0.65rem;
        }

        .captcha-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }

        .captcha-question {
            flex: 1;
            min-width: 160px;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .captcha-refresh {
            border: 1px solid rgba(212, 150, 10, 0.35);
            background: transparent;
            color: var(--gold);
            border-radius: 999px;
            padding: 0.5rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .captcha-refresh:hover {
            background: rgba(212, 150, 10, 0.12);
            border-color: var(--gold);
        }

        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .form-submit:disabled {
            cursor: wait;
            opacity: 0.72;
            transform: none;
        }

        /* GRID FORM */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* BUTTON */
        .form-submit {
            width: 100%;
            background: transparent;
            color: var(--gold);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;

            padding: 1rem;
            border: 1px solid rgba(212, 150, 10, 0.5);
            border-radius: 6px;

            cursor: pointer;
            position: relative;
            overflow: hidden;

            transition: all 0.35s ease;
        }

        /* HOVER BACKGROUND SMOOTH */
        .form-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.35s ease;
            z-index: 0;
        }

        /* HOVER EFFECT */
        .form-submit:hover::before {
            transform: scaleX(1);
        }

        .form-submit span {
            position: relative;
            z-index: 1;
        }

        .form-submit:hover {
            background: var(--gold-light);
            transform: translateY(-2px);
            color: var(--navy);
            border-color: var(--gold);
        }

        .form-submit:hover span {
            letter-spacing: 0.12em;
        }

        /* ========================= */
        /* ANIMATION */
        /* ========================= */
        .hidden {
            opacity: 0;
            transform: translateY(40px);
        }

        .show {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.8s ease;
        }

        /* ========================= */
        /* RESPONSIVE */
        /* ========================= */
        @media (max-width: 992px) {
            .contact-inner {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── FOOTER ── */
        footer {
            background: #07111f;
            padding: 2rem 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .footer-brand {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .footer-copy {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.3);
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .footer-links a {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--gold);
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp 0.7s ease forwards;
        }

        .fade-up-delay-1 {
            animation-delay: 0.15s;
            opacity: 0;
        }

        .fade-up-delay-2 {
            animation-delay: 0.3s;
            opacity: 0;
        }

        .fade-up-delay-3 {
            animation-delay: 0.45s;
            opacity: 0;
        }

        .fade-up-delay-4 {
            animation-delay: 0.6s;
            opacity: 0;
        }

        .logo-unico img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            display: block;
        }

        .logo-unico {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #clients {
            background: var(--white);
            padding: 4rem 0;
            overflow: hidden;
            border-top: 1px solid rgba(15, 30, 60, 0.08);
            border-bottom: 1px solid rgba(15, 30, 60, 0.08);
            position: relative;
        }

        /* FADE KIRI KANAN */
        #clients::before,
        #clients::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 120px;
            z-index: 2;
            pointer-events: none;
        }

        #clients::before {
            left: 0;
            background: linear-gradient(to right, var(--white), transparent);
        }

        #clients::after {
            right: 0;
            background: linear-gradient(to left, var(--white), transparent);
        }

        /* LABEL */
        .clients-label {
            text-align: center;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 2.5rem;
        }

        /* MARQUEE */
        .marquee-track {
            display: flex;
            width: max-content;
            animation: marquee-scroll 25s linear infinite;
        }

        .marquee-track:hover {
            animation-play-state: paused;
        }

        @keyframes marquee-scroll {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        /* ITEM */
        .marquee-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 1rem;
            flex-shrink: 0;
        }

        /* LOGO */
        .marquee-logo {
            height: 60px;
            /* kecil & konsisten */
            width: auto;
            max-width: 120px;
            object-fit: contain;

            filter: grayscale(1) opacity(0.5);
            transition: all 0.3s ease;
        }

        /* HOVER EFFECT */
        .marquee-logo:hover {
            filter: grayscale(0) opacity(1);
            transform: scale(1.08);
        }

        /* ── EXTRA ANIMATIONS ── */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes pulse-gold {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(212, 150, 10, 0.4);
            }

            50% {
                box-shadow: 0 0 0 12px rgba(212, 150, 10, 0);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        @keyframes spin-slow {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes slide-in-left {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slide-in-right {
            from {
                opacity: 0;
                transform: translateX(40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes count-up {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.8);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes border-glow {

            0%,
            100% {
                border-color: rgba(212, 150, 10, 0.3);
            }

            50% {
                border-color: rgba(212, 150, 10, 0.9);
                box-shadow: 0 0 20px rgba(212, 150, 10, 0.2);
            }
        }

        /* Hero card float */
        .hero-card {
            animation: float 4s ease-in-out infinite;
        }

        /* Hero stat nums pop in */
        .hero-stat-num {
            animation: count-up 0.6s ease both;
        }

        /* Nav logo pulse */
        .nav-logo .flame {
            animation: pulse-gold 3s ease-in-out infinite;
        }

        /* Gold accent shimmer on section titles */
        .section-tag::before {
            background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
            background-size: 200% auto;
            animation: shimmer 2.5s linear infinite;
        }

        /* Misi items stagger */
        .misi-item:nth-child(1) {
            animation: slide-in-right 0.5s 0.1s ease both;
        }

        .misi-item:nth-child(2) {
            animation: slide-in-right 0.5s 0.2s ease both;
        }

        .misi-item:nth-child(3) {
            animation: slide-in-right 0.5s 0.3s ease both;
        }

        .misi-item:nth-child(4) {
            animation: slide-in-right 0.5s 0.4s ease both;
        }

        /* Produk card hover glow */
        .produk-card:hover {
            box-shadow:
                0 20px 50px rgba(15, 30, 60, 0.25),
                0 0 0 1px rgba(212, 150, 10, 0.2);
        }

        /* Industri card accent line animates on hover */
        .industri-card:hover .industri-card-accent {
            width: 48px;
            transition: width 0.3s ease;
        }

        .industri-card-accent {
            transition: width 0.3s ease;
        }

        /* About badge pulse */
        .about-badge {
            animation: float 5s ease-in-out infinite;
        }

        /* Kontak form border glow on focus-within */
        .kontak-form:focus-within {
            border-color: rgba(212, 150, 10, 0.35);
            box-shadow: 0 0 30px rgba(212, 150, 10, 0.08);
            transition:
                border-color 0.4s,
                box-shadow 0.4s;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            #hero {
                grid-template-columns: 1fr;
            }

            .hero-right {
                display: none;
            }

            #about {
                grid-template-columns: 1fr;
            }

            .about-visual {
                height: 300px;
            }

            .vm-grid {
                grid-template-columns: 1fr;
            }

            .industri-grid {
                grid-template-columns: 1fr 1fr;
            }

            .kontak-inner {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            nav {
                padding: 1rem 5%;
            }

            .nav-links {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            footer {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <!-- NAV -->
    <nav class="navbar">
        <a href="#" class="nav-logo">
            <img src="/Unico/images/logo-unico.png" alt="Logo Unico" />
            <div class="logo-text">
                <strong>UNICO</strong>
                <span>Tractors Indonesia</span>
            </div>
        </a>

        <ul class="nav-links">
            <li><a href="#about">About</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>

        <a href="#contact" class="nav-cta">Contact Us</a>
    </nav>

    <!-- HERO -->
    <section id="hero">
        <div class="hero-bg-pattern"></div>

        <!-- LEFT CONTENT -->
        <div class="hero-left">
            <div class="hero-tag">Authorized Komatsu Spare Parts</div>

            <h1 class="hero-title">
                Reliable Parts
                <span>High Performance</span>
                Guaranteed
            </h1>

            <p class="hero-desc">
                Trusted distributor of genuine Komatsu spare parts for excavators, bulldozers,
                wheel loaders, and dump trucks. Supporting Indonesia’s industries with
                consistent quality and reliability since 2010.
            </p>

            <div class="hero-actions">
                <a href="#products" class="btn-primary">Explore Products</a>
                <a href="#contact" class="btn-outline">Contact Us</a>
            </div>

            <div class="hero-stats">
                <div>
                    <div class="hero-stat-num">15+</div>
                    <div class="hero-stat-label">Years of Experience</div>
                </div>
                <div>
                    <div class="hero-stat-num">5+</div>
                    <div class="hero-stat-label">Industry Sectors</div>
                </div>
                <div>
                    <div class="hero-stat-num">100%</div>
                    <div class="hero-stat-label">Genuine Parts</div>
                </div>
            </div>
        </div>

        <!-- RIGHT IMAGE -->
        <div class="hero-right">
            <img src="/Unico/images/bg_header.png" alt="Heavy Equipment" />

            <div class="hero-card">
                <div class="hero-card-title">International Quality Standards</div>
            </div>
        </div>
    </section>

    <!-- CLIENT LOGOS MARQUEE -->
    <!-- <section id="clients">
            <div class="clients-label">Dipercaya oleh Perusahaan Terkemuka</div>

            <div class="marquee-track">
                <div class="marquee-item">
                    <img src="images/client/1.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/2.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/3.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/4.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/5.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/6.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/7.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/8.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/1.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/2.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/3.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/4.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/5.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/6.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/7.png" class="marquee-logo" />
                </div>
                <div class="marquee-item">
                    <img src="images/client/8.png" class="marquee-logo" />
                </div>
            </div>
        </section> -->

    <!-- ABOUT -->
    <section id="about">
        <!-- LEFT VISUAL -->
        <div class="about-visual">
            <img src="/Unico/images/gudang.png" alt="Warehouse Operations" />

            <div class="about-visual-overlay">
                <div class="about-badge">
                    <div class="about-badge-num">2010</div>
                    <div class="about-badge-text">Established</div>
                </div>
            </div>
        </div>

        <!-- RIGHT CONTENT -->
        <div class="about-content">
            <div class="section-tag">About Us</div>

            <h2 class="section-title">Your Trusted Partner in Heavy Equipment Solutions</h2>

            <p>
                Founded in 2010, PT. Unico Tractors Indonesia specializes in supplying
                <strong>genuine Komatsu spare parts</strong> for a wide range of heavy
                equipment.
            </p>

            <p>
                With a strong commitment to authenticity and quality, we have become a reliable
                partner for industries such as construction, mining, and logistics across
                Indonesia.
            </p>

            <p>
                Backed by over a decade of experience, we continue to grow by delivering
                professional service, timely distribution, and dependable technical support.
            </p>

            <!-- PILLARS -->
            <div class="about-pillars">
                <div class="pillar">
                    <div class="pillar-title">Guaranteed Quality</div>
                    <div class="pillar-text">
                        Meeting international standards for optimal performance
                    </div>
                </div>

                <div class="pillar">
                    <div class="pillar-title">On-Time Delivery</div>
                    <div class="pillar-text">
                        Integrated distribution network across Indonesia
                    </div>
                </div>

                <div class="pillar">
                    <div class="pillar-title">Technical Support</div>
                    <div class="pillar-text">
                        Professional team ready to assist your operations
                    </div>
                </div>

                <div class="pillar">
                    <div class="pillar-title">Competitive Pricing</div>
                    <div class="pillar-text">Best value without compromising quality</div>
                </div>
            </div>
        </div>
    </section>

    <!-- VISI MISI -->
    <section id="vision-mission">
        <div class="vm-header">
            <div class="section-tag">Our Direction</div>
            <h2 class="section-title">Vision & Mission</h2>
        </div>

        <div class="vm-grid">
            <!-- VISION -->
            <div class="vm-vision">
                <div class="vm-vision-content">
                    <div class="vm-label">Vision</div>
                    <div class="vm-text">
                        To become the most trusted and leading provider of heavy equipment spare
                        parts solutions in Indonesia.
                    </div>
                </div>
                <div class="vm-big">V</div>
            </div>

            <!-- MISSION -->
            <div class="vm-mission">
                <div class="vm-label">Mission</div>

                <div class="mission-item">
                    <div class="mission-num">01</div>
                    <div class="mission-text">
                        Empower our partners’ operations and growth by delivering reliable and
                        high-quality spare parts solutions.
                    </div>
                </div>

                <div class="mission-item">
                    <div class="mission-num">02</div>
                    <div class="mission-text">
                        Provide excellent service through speed, accuracy, and competitive
                        pricing.
                    </div>
                </div>

                <div class="mission-item">
                    <div class="mission-num">03</div>
                    <div class="mission-text">
                        Ensure consistent availability of premium spare parts to support
                        uninterrupted operations.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUK -->
    <section id="products">
        <div class="products-header">
            <div class="section-tag">What We Provide</div>
            <h2 class="section-title">Our Products</h2>

            <p class="products-intro">
                PT. Unico Tractors Indonesia specializes in supplying genuine Komatsu spare
                parts to support a wide range of heavy equipment such as excavators, bulldozers,
                wheel loaders, and dump trucks.
            </p>
        </div>

        <div class="products-grid">
            <div class="product-card">
                <div class="product-image">
                    <img src="images/product/1.png" alt="Engine Components" />
                </div>
                <div class="product-content">
                    <div class="product-title">Engine Components</div>
                    <ul class="product-list">
                        <li>Pistons</li>
                        <li>Rings</li>
                        <li>Gaskets</li>
                    </ul>
                </div>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="images/product/2.png" alt="Hydraulic System" />
                </div>
                <div class="product-content">
                    <div class="product-title">Hydraulic System</div>
                    <ul class="product-list">
                        <li>Pumps</li>
                        <li>Cylinders</li>
                        <li>Seals</li>
                    </ul>
                </div>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="images/product/3.png" alt="Electrical System" />
                </div>
                <div class="product-content">
                    <div class="product-title">Electrical System</div>
                    <ul class="product-list">
                        <li>Sensors</li>
                        <li>Cables</li>
                        <li>Starters</li>
                    </ul>
                </div>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="images/product/4.png" alt="Filtration System" />
                </div>
                <div class="product-content">
                    <div class="product-title">Filtration System</div>
                    <ul class="product-list">
                        <li>Oil Filters</li>
                        <li>Air Filters</li>
                        <li>Fuel Filters</li>
                    </ul>
                </div>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="images/product/5.png" alt="Wear Parts" />
                </div>
                <div class="product-content">
                    <div class="product-title">Wear Parts</div>
                    <ul class="product-list">
                        <li>Bucket Teeth</li>
                        <li>Cutting Edges</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- INDUSTRI -->
    <section id="industries">
        <div class="industries-header">
            <div class="section-tag">Industries</div>
            <h2 class="section-title">Sectors We Serve</h2>

            <p class="industries-desc">
                Through an integrated distribution network and professional technical support,
                we actively support various industries by delivering reliable and timely spare
                parts solutions.
            </p>
        </div>

        <div class="industries-grid">
            <div class="industry-card">
                <div class="industry-bg">
                    <img src="/Unico/images/sektor/1.png" alt="Forestry" />
                </div>
                <div class="industry-overlay">
                    <div>
                        <div class="industry-accent"></div>
                        <div class="industry-name">Forestry</div>
                    </div>
                </div>
            </div>

            <div class="industry-card">
                <div class="industry-bg">
                    <img src="/Unico/images/sektor/2.png" alt="Plantation" />
                </div>
                <div class="industry-overlay">
                    <div>
                        <div class="industry-accent"></div>
                        <div class="industry-name">Plantation</div>
                    </div>
                </div>
            </div>

            <div class="industry-card">
                <div class="industry-bg">
                    <img src="/Unico/images/sektor/3.png" alt="Agriculture" />
                </div>
                <div class="industry-overlay">
                    <div>
                        <div class="industry-accent"></div>
                        <div class="industry-name">Agriculture</div>
                    </div>
                </div>
            </div>

            <div class="industry-card">
                <div class="industry-bg">
                    <img src="/Unico/images/sektor/4.png" alt="Construction" />
                </div>
                <div class="industry-overlay">
                    <div>
                        <div class="industry-accent"></div>
                        <div class="industry-name">Construction</div>
                    </div>
                </div>
            </div>

            <div class="industry-card">
                <div class="industry-bg">
                    <img src="/Unico/images/sektor/5.png" alt="Mining" />
                </div>
                <div class="industry-overlay">
                    <div>
                        <div class="industry-accent"></div>
                        <div class="industry-name">Mining</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT -->
    <section id="contact">
        <div class="contact-inner">
            <!-- LEFT -->
            <div>
                <div class="section-tag" style="color: var(--gold)">Contact Us</div>

                <h2 class="contact-tagline">Grow Together, <span>Go Further</span></h2>

                <p class="contact-desc">
                    We believe strong partnerships are the foundation of industrial growth. Our
                    team is ready to support your business needs with reliable solutions.
                </p>

                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div>
                            <div class="contact-item-label">Phone</div>
                            <div class="contact-item-value">(+62)21-384-3888</div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <div class="contact-item-label">Email</div>
                            <div class="contact-item-value">info@unicotractors.com</div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fa-solid fa-globe"></i>
                        </div>
                        <div>
                            <div class="contact-item-label">Website</div>
                            <div class="contact-item-value">www.unicotractors.com</div>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <div class="contact-item-label">Address</div>
                            <div class="contact-item-value">
                                Jln. Krekot Jaya Molek H 5D,<br />
                                Jakarta, Indonesia 10710
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT (FORM) -->
            <div class="contact-form">
                <h3>Send a Message</h3>

                <form id="contact-form" method="post" action="<?php echo esc(strtok($_SERVER['REQUEST_URI'], '?')); ?>#contact" novalidate>
                    <div id="form-feedback" class="form-feedback <?php echo esc($feedbackType); ?>" aria-live="polite"><?php echo esc($feedbackMessage); ?></div>

                    <input type="hidden" name="form_token" value="<?php echo esc($formToken); ?>" />
                    <input type="hidden" name="form_started_at" value="<?php echo esc($formStartedAt); ?>" />

                    <div class="honeypot" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" />
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <input type="text" name="name" placeholder="Your Name" minlength="2" maxlength="80"
                                autocomplete="name" required value="<?php echo esc($values['name']); ?>"
                                class="<?php echo isset($fieldErrors['name']) ? 'is-invalid' : ''; ?>" />
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Your Email" maxlength="120"
                                autocomplete="email" required value="<?php echo esc($values['email']); ?>"
                                class="<?php echo isset($fieldErrors['email']) ? 'is-invalid' : ''; ?>" />
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="text" name="company" placeholder="Company" maxlength="120"
                            autocomplete="organization" value="<?php echo esc($values['company']); ?>"
                            class="<?php echo isset($fieldErrors['company']) ? 'is-invalid' : ''; ?>" />
                    </div>

                    <div class="form-group">
                        <input type="text" name="product" placeholder="Product / Spare Part Needed" maxlength="120"
                            value="<?php echo esc($values['product']); ?>"
                            class="<?php echo isset($fieldErrors['product']) ? 'is-invalid' : ''; ?>" />
                    </div>

                    <div class="captcha-box">
                        <label class="captcha-label" for="captcha-answer">Security Check</label>
                        <div class="captcha-row">
                            <div id="captcha-question" class="captcha-question"><?php echo esc($captchaQuestion); ?></div>
                            <a href="?refresh_captcha=1#contact" id="captcha-refresh" class="captcha-refresh">Refresh</a>
                        </div>
                        <div class="form-group" style="margin-bottom: 0">
                            <input type="text" id="captcha-answer" name="captcha-answer" placeholder="Answer"
                                inputmode="numeric" autocomplete="off" required value=""
                                class="<?php echo isset($fieldErrors['captcha']) ? 'is-invalid' : ''; ?>" />
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea name="message" rows="4" placeholder="Your Message..." minlength="20" maxlength="1500"
                            required class="<?php echo isset($fieldErrors['message']) ? 'is-invalid' : ''; ?>"><?php echo esc($values['message']); ?></textarea>
                    </div>

                    <button type="submit" class="form-submit">
                        <span>Send Message →</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="footer-brand">PT. Unico Tractors Indonesia</div>

        <div class="footer-copy">© 2026 PT. Unico Tractors Indonesia. All Rights Reserved.</div>

        <ul class="footer-links">
            <li><a href="#about">About</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
    </footer>
    <script>
        /* ========================= */
        /* SCROLL REVEAL */
        /* ========================= */
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((el) => {
                    if (el.isIntersecting) {
                        el.target.classList.add('show')
                        observer.unobserve(el.target)
                    }
                })
            }, {
                threshold: 0.15
            }
        )

        /* ELEMENTS ANIMATE */
        document
            .querySelectorAll('.contact-item, .contact-form, .form-group')
            .forEach((el, i) => {
                el.classList.add('hidden')
                el.style.transitionDelay = (i % 5) * 0.08 + 's'
                observer.observe(el)
            })

        /* ========================= */
        /* HEADER ANIMATION */
        /* ========================= */
        const headerObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((el) => {
                    if (el.isIntersecting) {
                        el.target.classList.add('show')
                        headerObserver.unobserve(el.target)
                    }
                })
            }, {
                threshold: 0.2
            }
        )

        document
            .querySelectorAll('.contact-tagline, .contact-desc, .section-tag')
            .forEach((el) => {
                el.classList.add('hidden')
                headerObserver.observe(el)
            })

        /* ========================= */
        /* NAV SCROLL EFFECT */
        /* ========================= */
        const nav = document.querySelector('nav')

        window.addEventListener('scroll', () => {
            if (window.scrollY > 60) {
                nav.style.background = 'rgba(15,30,60,0.98)'
                nav.style.boxShadow = '0 6px 30px rgba(0,0,0,0.25)'
            } else {
                nav.style.background = 'rgba(15,30,60,0.92)'
                nav.style.boxShadow = 'none'
            }
        })
    </script>
</body>

</html>