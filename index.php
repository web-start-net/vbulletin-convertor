<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Migration Tool → vBulletin 6</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    body {
      background: #f8f9fa;
    }
    .migration-card {
      cursor: pointer;
      transition: all 0.3s ease;
      border-radius: 12px;
    }
    .migration-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }
    .disabled-card {
      opacity: 0.6;
      cursor: not-allowed;
      position: relative;
    }
    .coming-soon-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0,0,0,0.7);
      color: #fff;
      padding: 3px 8px;
      font-size: 12px;
      border-radius: 6px;
    }
    .hidden {
      display: none;
    }
  </style>
</head>
<body>

<div class="container py-5">

  <h2 class="text-center mb-5">🚀 Migration Tool → vBulletin 6</h2>

  <!-- Step 0: Choose Source Platform -->
  <div id="chooseSystem" class="text-center">
    <h5 class="mb-4">Select the source platform you want to migrate from:</h5>
    <div class="row justify-content-center g-4">

      <div class="col-md-3">
        <div class="card migration-card border-primary text-primary" onclick="selectSystem('xenforo')">
          <div class="card-body">
            <h4>🧩 XenForo</h4>
            <p>Fully supported for migration</p>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card migration-card border-warning text-warning disabled-card">
          <div class="coming-soon-badge">Coming Soon</div>
          <div class="card-body">
            <h4>💬 phpBB</h4>
            <p>Migration module in development</p>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card migration-card border-secondary text-secondary disabled-card">
          <div class="coming-soon-badge">Coming Soon</div>
          <div class="card-body">
            <h4>💠 MyBB</h4>
            <p>Migration module in development</p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Step 1: Load selected migration form -->
  <div id="migrationFormContainer" class="hidden mt-5"></div>

</div>

<script>
function selectSystem(system) {
  if(system !== 'xenforo') {
    alert("⚠️ This migration module is still under development.");
    return;
  }

  // Load the XenForo form dynamically
  $('#chooseSystem').fadeOut(300, function(){
    $.get('modules/xenforo/form.php', function(html){
      $('#migrationFormContainer').html(html).fadeIn(300);
    }).fail(function(){
      $('#migrationFormContainer').html('<div class="alert alert-danger">Error loading XenForo migration form.</div>');
    });
  });
}
</script>

</body>
</html>
