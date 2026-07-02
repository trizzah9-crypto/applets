<?php
session_start();
if (!isset($_SESSION['user_id'])) header('Location: login.html');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Select Business</title>
<style>
  /* Reset */
  * {
    box-sizing: border-box;
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f7f8;
    margin: 0;
    padding: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 40px 20px;
    color: #333;
  }

  h2 {
    font-weight: 700;
    color: #004549;
    margin-bottom: 24px;
    text-align: center;
  }

  #list {
    width: 100%;
    max-width: 480px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    padding: 24px 20px;
    min-height: 150px;
  }

  .business-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    margin-bottom: 12px;
    border-radius: 6px;
    background: #e9f1f0;
    transition: background-color 0.2s ease;
    cursor: pointer;
  }
  .business-item:hover {
    background: #c2dedc;
  }

  .business-info {
    display: flex;
    flex-direction: column;
  }

  .business-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: #025659;
  }

  .business-role {
    font-size: 0.9rem;
    color: #6c757d;
    font-style: italic;
  }

  button.pick {
    background-color: #027d69;
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color 0.25s ease;
  }
  button.pick:hover {
    background-color: #025649;
  }

  a {
    color: #027d69;
    text-decoration: none;
    font-weight: 600;
  }
  a:hover {
    text-decoration: underline;
  }

  /* Loading & error styling */
  #list.loading {
    font-style: italic;
    color: #888;
    text-align: center;
  }
  #list.error {
    color: #c53030;
    font-weight: 600;
    text-align: center;
  }

  @media (max-width: 520px) {
    #list {
      padding: 20px 12px;
    }
    .business-item {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }
    button.pick {
      width: 100%;
    }
  }
</style>
</head>
<body>
    <img style="width: 150px;" src="Images/logo.png" alt="Logo" class="logo" />
<h2>Select a Business to Manage</h2>
<div id="list" class="loading">Loading...</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$.getJSON('ajax/get_businesses.php', function(res){
  const $list = $('#list');
  $list.removeClass('loading error');
  if(res.status !== 'ok') { 
    $list.addClass('error').text('Error loading businesses'); 
    return; 
  }
  const bs = res.businesses;
  if(bs.length === 0) { 
    $list.html('No businesses yet. <a href="create_first_business.php">Create one</a>'); 
    return; 
  }
  let html = '';
  bs.forEach(b => {
    html += `
      <div class="business-item" data-id="${b.id}" data-role="${b.role}">
        <div class="business-info">
          <span class="business-name">${b.name}</span>
          <span class="business-role">${b.role}</span>
        </div>
        <button class="pick">Open</button>
      </div>`;
  });
  $list.html(html);
});

$(document).on('click', '.pick', function(){
  const $parent = $(this).closest('.business-item');
  const id = $parent.data('id'), role = $parent.data('role');
  $.post('ajax/set_business.php', { business_id: id, role: role }, function(r){
    if(r.status === 'ok') location.href = 'dashboard.php';
    else alert('Failed to set business');
  }, 'json');
});
</script>
</body>
</html>
