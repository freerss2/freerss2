<?php
  /* Redirect either to login page or personal area */
  session_start();
  setcookie('mobile_client', '1', time() + 60*60*24*30, '/');
  if ( $_SESSION && $_SESSION['user_id'] ) {
    console.log('session Ok');
    header("Location: /personal/"); /* Redirect browser */
    exit();
  } // else
  header("Location: /login/connect.php"); /* Redirect browser */
  exit();
?>