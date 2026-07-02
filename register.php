<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
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

    /* LEFT SIDE with image & gradient */
    .left {
      width: 50%;
      background: 
        linear-gradient(to bottom, rgba(37, 117, 252, 0.75), rgba(106, 17, 203, 0.85)),
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
      user-select: none;
    }

    .left p {
      font-size: 17px;
      line-height: 1.5;
      font-weight: 500;
      opacity: 0.95;
      margin: 4px 0;
      user-select: none;
    }

    /* RIGHT SIDE FORM */
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

    h2 {
      margin-bottom: 26px;
      color: #2575fc;
      font-weight: 700;
      letter-spacing: 1px;
    }

    input.form-control {
      width: 100%;
      padding: 12px 14px;
      margin-bottom: 18px;
      border-radius: 10px;
      border: 1.5px solid #d3d3d3;
      font-size: 15px;
      transition: all 0.3s ease;
      box-shadow: inset 2px 2px 8px #d4d9ff, inset -2px -2px 8px #f5f8ff;
    }

    input.form-control:focus {
      border-color: #2575fc;
      box-shadow: 0 0 8px 2px #2575fc;
      background-color: #f0f6ff;
      outline: none;
    }

    button {
      width: 100%;
      background: linear-gradient(45deg, #2575fc, #6a11cb);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 17px;
      font-weight: 700;
      cursor: pointer;
      padding: 14px 0;
      box-shadow: 0 8px 18px rgba(101, 52, 255, 0.5);
      transition: 0.25s ease;
      margin-top: 10px;
      user-select: none;
    }

    button:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 25px rgba(101, 52, 255, 0.75);
    }

    #msg {
      color: #c62828;
      font-weight: 600;
      min-height: 20px;
      margin-top: 8px;
    }

    p.mt-3 {
      margin-top: 16px;
      font-size: 14px;
      color: #555;
    }

    p.mt-3 a {
      color: #2575fc;
      font-weight: 600;
      text-decoration: none;
    }

    p.mt-3 a:hover {
      text-decoration: underline;
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
        height: 250px;
      }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <!-- LEFT SIDE -->
    <div class="left">
      <h1>Create Your Account</h1>
      <p>Secure. Reliable. Professional.</p>
      <p>Your data is protected with enterprise-grade encryption.</p>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right">
      <img style="width: 150px; margin-left: 50%; transform: translate(-50%);" src="Images/logo.png" alt="Logo" class="logo" />

      <form id="regForm" autocomplete="off">
        <h2>Register</h2>
        <input name="name" class="form-control" placeholder="Full name" required autocomplete="name" />
        <input name="email" type="email" class="form-control" placeholder="Email" required autocomplete="email" />
        <input name="password" type="password" class="form-control" placeholder="Password" required autocomplete="new-password" />
        <div id="msg"></div>
        <button type="submit">Register</button>
      </form>
      <p class="mt-3">Already have an account? <a href="login.php">Login</a></p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function showMsg(text, type='error') {
      const msg = $('#msg');
      msg.text(text);
      if(type === 'error') {
        msg.css('color', '#c62828');
      } else {
        msg.css('color', '#2e7d32');
      }
    }

    $('#regForm').on('submit', function(e){
      e.preventDefault();
      showMsg('');
      const data = $(this).serialize();
      $.post('ajax/register.php', data, function(res){
        if(res.status === 'ok'){
          showMsg('Registered — redirecting...', 'success');
          setTimeout(() => window.location.href = 'business_select.php', 800);
        } else {
          showMsg(res.message || 'Registration failed', 'error');
        }
      }, 'json').fail(function(xhr){
        let txt = 'Network error';
        try { txt = xhr.responseText ? JSON.parse(xhr.responseText).message : txt; } catch(e) {}
        showMsg(txt, 'error');
      });
    });
  </script>
</body>
</html>
