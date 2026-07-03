<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="icon" type="image/png" sizes="16x16" href="./Images/logo.png" />

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #eef2f3;
      padding: 20px;
    }

    /* MAIN WRAPPER */
    .wrapper {
      display: flex;
      width: 90%;
      max-width: 1000px;
      height: 520px;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 15px 40px rgba(0,0,0,0.12);
      overflow: hidden;
      animation: fadeSlide 0.7s ease forwards;
    }

    @keyframes fadeSlide {
      0% { opacity:0; transform: translateY(40px); }
      100% { opacity:1; transform: translateY(0); }
    }

    /* LEFT SIDE */
    .left {
      width: 50%;
       background: 
        linear-gradient(to bottom, rgba(54, 235, 196, 0.4), rgba(0, 0, 0, 0.75)),
        url('images/background.png') center/cover no-repeat;
      padding: 50px 35px;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      text-align: center;
      animation: fadeLeft 1s ease forwards;
    }

    @keyframes fadeLeft {
      0% { opacity:0; transform: translateX(-40px); }
      100% { opacity:1; transform: translateX(0); }
    }

    .left h1 {
      font-size: 38px;
      font-weight: 800;
      margin-bottom: 16px;
      text-shadow: 0 2px 6px rgba(0,0,0,0.25);
    }

    .left p {
      font-size: 17px;
      line-height: 1.5;
      font-weight: 500;
      opacity: 0.95;
    }

    /* RIGHT SIDE (FORM) */
    .right {
      width: 50%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      text-align: center;
    }

    form {
      width: 100%;
      text-align: center;
    }

    .logo {
      width: 120px;
      margin-bottom: 20px;
      filter: drop-shadow(0 0 3px rgba(0,0,0,0.15));
    }

    h2 {
      margin-bottom: 26px;
      color: #11998e;
      font-weight: 700;
      letter-spacing: 1px;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px 14px;
      margin-bottom: 18px;
      border-radius: 10px;
      border: 1.5px solid #d3d3d3;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    input:focus {
      border-color: #11998e;
      box-shadow: 0 0 8px rgba(17,153,142,0.4);
      background-color: #e5f7f2;
    }

    .password-container {
      position: relative;
      width: 100%;
    }

    .password-container input {
      width: 100%;
      padding-right: 45px;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #777;
      font-size: 18px;
    }

    button {
      width: 100%;
      background: linear-gradient(45deg, #38ef7d, #11998e);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 17px;
      font-weight: 700;
      cursor: pointer;
      padding: 14px 0;
      box-shadow: 0 8px 18px rgba(56, 239, 125, 0.5);
      transition: 0.25s ease;
      margin-top: 10px;
    }

    button:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    }

    #errorMsg {
      color: #c62828;
      font-weight: 600;
      min-height: 20px;
    }

    p#noAccount {
      margin-top: 16px;
      font-size: 14px;
      color: #555;
    }

    p#noAccount a {
      color: #11998e;
      font-weight: 600;
      text-decoration: none;
    }

    @media (max-width: 850px) {
      .wrapper {
        flex-direction: column;
        height: auto;
      }
      .left, .right {
        width: 100%;
      }
      .left {
        padding: 40px 25px;
      }
    }

  </style>
</head>

<body>

<div class="wrapper">

  <!-- LEFT SIDE -->
  <div class="left">
  <h1>Welcome Back</h1>
  <p style="font-size:18px; font-weight:700;">Your Secure Dashboard Awaits.</p>
  <p>Powered by enterprise-grade encryption, system reliability, and seamless admin control.</p>
</div>


  <!-- RIGHT SIDE -->
  <div class="right">
    <form id="loginForm" >
      <img style="width: 150px;" src="images/logo.png" alt="Logo" class="logo" />
      <h2>Admin Login</h2>

      <input type="email" name="email" placeholder="Email" required />

      <div class="password-container">
        <input type="password" id="password" name="password" placeholder="Password" required />
        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
      </div>

      <div id="errorMsg"></div>

      <button type="submit">Login</button>

      <p id="noAccount">Don't have an account? <a href="register.php">Register here</a></p>
    </form>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
  // Toggle password
  const passwordInput = document.getElementById("password");
  const togglePassword = document.getElementById("togglePassword");

  togglePassword.addEventListener("click", () => {
    const type = passwordInput.type === "password" ? "text" : "password";
    passwordInput.type = type;
    togglePassword.classList.toggle("fa-eye-slash");
  });

  // Submit Login
  $("#loginForm").on("submit", function (e) {
    e.preventDefault();
    $("#errorMsg").text("");

    $.ajax({
      url: "ajax/login.php",
      type: "POST",
      data: $(this).serialize(),
      dataType: "json",
      success: function (res) {
        if (res.status === "ok") {
          window.location.href = "dashboard.php";
        } else {
          $("#errorMsg").text(res.message || "Login failed");
        }
      },
      error: function () {
        $("#errorMsg").text("Network error");
      },
    });
  });
</script>

</body>
</html>
