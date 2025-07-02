<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-T">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Arturomania Systems</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to right, #6a11cb, #2575fc);
            color: white;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        header {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 1.8em;
            font-weight: bold;
        }
        .logo img {
            height: 50px; /* Default height, adjust as needed */
            vertical-align: middle;
        }
        nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            border: 1px solid white;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s;
        }
        nav a:hover {
            background-color: white;
            color: #2575fc;
        }
        .hero {
            text-align: center;
            padding: 80px 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .hero p {
            font-size: 1.4em;
            margin-bottom: 40px;
            max-width: 600px;
        }
        .cta-buttons a {
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            background-color: #ff4081; /* A vibrant call-to-action color */
            border-radius: 5px;
            font-size: 1.2em;
            margin: 0 10px;
            transition: background-color 0.3s;
        }
        .cta-buttons a:hover {
            background-color: #f50057;
        }
        footer {
            text-align: center;
            padding: 20px;
            background-color: rgba(0,0,0,0.2);
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <!-- Placeholder for logo -->
            <img src="aslogo.png" alt="Arturomania Systems Logo">
            Arturomania Systems
        </div>
        <nav>
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
        </nav>
    </header>

    <section class="hero">
        <h1>Empowering Educational Excellence</h1>
        <p>Streamline your school's management, reporting, and analysis with Arturomania Systems. Designed for efficiency and insight.</p>
        <div class="cta-buttons">
            <a href="signup.php">Get Started Now</a>
        </div>
    </section>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Arturomania Systems. All rights reserved.</p>
    </footer>
</body>
</html>
