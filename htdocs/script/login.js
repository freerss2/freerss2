/* --------------- *\
   "Login" page
   JavaScript code
\* --------------- */

// show error dialog
function showError(msg) {
  var errorModal = new bootstrap.Modal(document.getElementById('errorDialog'), {focus: true});
  var elm = document.getElementById('errorDialogContent');
  if (elm) { elm.innerHTML = msg; }
  errorModal.show();
}

// send phase1 login info to back-end
function signIn() {
  var elm1 = document.getElementById('login_email');
  var elm2 = document.getElementById('login_password');
  if (! elm1 || !elm1.value) { return; }
  if (! elm2 || !elm2.value) { return; }
  var email = elm1.value;
  var password = elm2.value;
  // console.log('trigger search for: '+tofind);
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/\?.*/, '');
  console.log('sending request...');
  var new_url = base_url + '../api/login/?function=first_stage&login='+email;
  var buf = httpGet(new_url);
  if(buf.startsWith('Error:')) {
      console.log(buf);
      // window.alert(buf);
      showError(buf);
      return;
  }
  // read temp_key from buf
  var temp_key = buf.replace(/^\s+|\s+$/g, '');;
  // console.log("got temp_key="+temp_key);
  // calculate MD5 from password, add temp_key and MD5 again
  var encripted_password = md5(temp_key+md5(password));
  // console.log("encripted_password="+encripted_password);
  // go to stage2
  new_url = base_url +
    '../api/login/?function=second_stage&login=' + email +
    '&password=' + encripted_password;
  var buf = httpGet(new_url);
  if(buf.startsWith('Error:')) {
      console.log(buf);
      // window.alert(buf);
      showError(buf);
      return;
  }
  console.log(buf);
  if (buf !== '0') {
    new_url = base_url + '../personal';
    console.log(new_url);
    window.location.href = new_url;
  }
}

function createAccount() {
  var elm1 = document.getElementById('email');
  var elm2 = document.getElementById('name');
  var elm3 = document.getElementById('no_mail_send');
  if (! elm1 || !elm1.value) { return; }
  if (! elm2 || !elm2.value) { return; }
  if (! elm3 ) { return; }
  var email = elm1.value;
  var name = elm2.value;
  var no_mail_send = elm2.checked;
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/\?.*/, '');

  new_url = base_url +
    '../api/create_account/?email=' + email +
    '&name=' + name + '&no_mail_send=' + no_mail_send;
  var buf = httpGet(new_url);
  if(buf.startsWith('Error:')) {
      console.log(buf);
      // window.alert(buf);
      showError(buf);
      return;
  }
  var infoModal = new bootstrap.Modal(document.getElementById('infoDialog'), {focus: true});
  document.getElementById('infoDialogContent').innerHTML = buf;
  // send results to dialog content
  infoModal.show();
}
