<?php
  /* Redirect either to login page or personal area */
  session_start();

  if ( $_SESSION && $_SESSION['user_id'] ) {
    header("Location: /personal/"); /* Redirect browser */
    exit();
  } // else
  header("Location: /login/"); /* Redirect browser */
  exit();
?>