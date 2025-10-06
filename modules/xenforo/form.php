<?php
session_start();
?>
<div class="card mb-4">
  <div class="card-header bg-primary text-white">Step 0: Database Connection (XenForo → vBulletin 6)</div>
  <div class="card-body">
    <form id="dbForm">
      <div class="row">
        <div class="col-md-6">
          <h5>XenForo Database</h5>
          <input type="text" class="form-control mb-2" name="xf_host" placeholder="Host" value="localhost">
          <input type="text" class="form-control mb-2" name="xf_user" placeholder="User">
          <input type="password" class="form-control mb-2" name="xf_pass" placeholder="Password">
          <input type="text" class="form-control mb-2" name="xf_db" placeholder="Database">
        </div>
        <div class="col-md-6">
          <h5>vBulletin Database</h5>
          <input type="text" class="form-control mb-2" name="vb_host" placeholder="Host" value="localhost">
          <input type="text" class="form-control mb-2" name="vb_user" placeholder="User">
          <input type="password" class="form-control mb-2" name="vb_pass" placeholder="Password">
          <input type="text" class="form-control mb-2" name="vb_db" placeholder="Database">
        </div>
      </div>
      <div class="d-flex justify-content-between mb-3">
        <button type="button" class="btn btn-primary" onclick="testConnection()">Test Connection</button>
        <button type="button" class="btn btn-danger" onclick="resetMigration()">Reset Migration</button>
      </div>
    </form>
    <div id="connectionResult" class="mt-3"></div>
  </div>
</div>

<!-- Migration Steps -->
<div id="stepGroups" class="card mb-3 d-none">
  <div class="card-header bg-warning text-white">Step 1: Migrate Groups</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar" id="groupsProgress" style="width:0%">0%</div></div>
    <button class="btn btn-warning" onclick="startGroups()">Start Groups Migration</button>
    <div id="groupsResult" class="mt-2"></div>
  </div>
</div>

<div id="stepUsers" class="card mb-3 d-none">
  <div class="card-header bg-success text-white">Step 2: Migrate Users</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar" id="usersProgress" style="width:0%">0%</div></div>
    <button class="btn btn-success" onclick="startUsers()">Start Users Migration</button>
    <div id="usersResult" class="mt-2"></div>
  </div>
</div>

<div id="stepForums" class="card mb-3 d-none">
  <div class="card-header bg-info text-white">Step 3: Migrate Forums</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar" id="forumsProgress" style="width:0%">0%</div></div>
    <button class="btn btn-info" onclick="startForums()">Start Forums Migration</button>
    <div id="forumsResult" class="mt-2"></div>
  </div>
</div>

<div id="stepThreads" class="card mb-3 d-none">
  <div class="card-header bg-secondary text-white">Step 4: Migrate Threads</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar" id="threadsProgress" style="width:0%">0%</div></div>
    <button class="btn btn-secondary" onclick="startThreads()">Start Threads Migration</button>
    <div id="threadsResult" class="mt-2"></div>
  </div>
</div>

<div id="stepPosts" class="card mb-3 d-none">
  <div class="card-header bg-dark text-white">Step 5: Migrate Posts</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar bg-light text-dark" id="postsProgress" style="width:0%">0%</div></div>
    <button class="btn btn-dark" onclick="startPosts()">Start Posts Migration</button>
    <div id="postsResult" class="mt-2"></div>
  </div>
</div>

<div id="stepAttachments" class="card mb-3 d-none">
  <div class="card-header bg-danger text-white">Step 6: Migrate Attachments</div>
  <div class="card-body">
    <div class="progress mb-2"><div class="progress-bar" id="attachmentsProgress" style="width:0%">0%</div></div>
    <button class="btn btn-danger" onclick="startAttachments()">Start Attachments Migration</button>
    <div id="attachmentsResult" class="mt-2"></div>
  </div>
</div>

<script>
// Test DB connection
function testConnection() {
  let dbFields = ['xf_host','xf_user','xf_pass','xf_db','vb_host','vb_user','vb_pass','vb_db'];
  dbFields.forEach(f => localStorage.setItem(f, $('input[name="'+f+'"]').val()));

  $.post('modules/xenforo/db_connection_ajax.php', $('#dbForm').serialize(), function(res){
    if(res.success){
      $('#connectionResult').html('<div class="alert alert-success">✅ Connection successful!</div>');
      $('#stepGroups').removeClass('d-none');
    } else {
      $('#connectionResult').html('<div class="alert alert-danger">❌ '+res.message+'</div>');
    }
  }, 'json');
}

// Migration steps handler
function migrateBatch(url, progressId, resultId, nextStepId=null){
  let step = progressId.replace('Progress','');
  let offset = parseInt(localStorage.getItem(step+'_offset')) || 0;
  let total = 0;

  function run(){
    $.ajax({
      url: url,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({offset: offset, limit:500}),
      success: function(data){
        if(total === 0) total = data.total || 0;
        offset += data.migrated;
        localStorage.setItem(step+'_offset', offset);
        let percent = total ? Math.round(offset/total*100) : 100;
        $('#'+progressId).css('width',percent+'%').text(percent+'%');

        if(offset < total){
          run();
        } else {
          $('#'+resultId).html('<div class="alert alert-success">✅ Completed!</div>');
          localStorage.removeItem(step+'_offset');
          if(nextStepId) $('#'+nextStepId).removeClass('d-none');
        }
      },
      error: function(xhr, status, error){
        $('#'+resultId).html('<div class="alert alert-danger">❌ Error: '+error+'</div>');
      }
    });
  }
  run();
}

function startGroups(){ migrateBatch('modules/xenforo/migrate_groups_ajax.php','groupsProgress','groupsResult','stepUsers'); }
function startUsers(){ migrateBatch('modules/xenforo/migrate_users_ajax.php','usersProgress','usersResult','stepForums'); }
function startForums(){ migrateBatch('modules/xenforo/migrate_forums_ajax.php','forumsProgress','forumsResult','stepThreads'); }
function startThreads(){ migrateBatch('modules/xenforo/migrate_threads_ajax.php','threadsProgress','threadsResult','stepPosts'); }
function startPosts(){ migrateBatch('modules/xenforo/migrate_posts_ajax.php','postsProgress','postsResult','stepAttachments'); }
function startAttachments(){ migrateBatch('modules/xenforo/migrate_attachments_ajax.php','attachmentsProgress','attachmentsResult'); }

// Reset migration data
function resetMigration(){
  let dbFields = ['xf_host','xf_user','xf_pass','xf_db','vb_host','vb_user','vb_pass','vb_db'];
  dbFields.forEach(f => localStorage.removeItem(f));
  let steps = ['groups','users','forums','threads','posts','attachments'];
  steps.forEach(s => localStorage.removeItem(s+'_offset'));

  $('input[name="xf_host"]').val('localhost');
  $('input[name="xf_user"]').val('');
  $('input[name="xf_pass"]').val('');
  $('input[name="xf_db"]').val('');
  $('input[name="vb_host"]').val('localhost');
  $('input[name="vb_user"]').val('');
  $('input[name="vb_pass"]').val('');
  $('input[name="vb_db"]').val('');

  steps.forEach(s=>$('#step'+capitalizeFirstLetter(s)).addClass('d-none'));
  $('#connectionResult, #groupsResult, #usersResult, #forumsResult, #threadsResult, #postsResult, #attachmentsResult').html('');
  $('#groupsProgress, #usersProgress, #forumsProgress, #threadsProgress, #postsProgress, #attachmentsProgress').css('width','0%').text('0%');
}

function capitalizeFirstLetter(str){ return str.charAt(0).toUpperCase()+str.slice(1); }

// Restore DB credentials if available
$(document).ready(function(){
  let dbFields = ['xf_host','xf_user','xf_pass','xf_db','vb_host','vb_user','vb_pass','vb_db'];
  dbFields.forEach(f=>{
    let val = localStorage.getItem(f);
    if(val) $('input[name="'+f+'"]').val(val);
  });
});
</script>
