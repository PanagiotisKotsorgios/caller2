<?php
require __DIR__ . '/config.php'; require __DIR__ . '/includes/functions.php'; require __DIR__ . '/includes/auth.php';
require_admin(); verify_csrf(); $id=(int)($_POST['id']??0); if($id){$s=db()->prepare('DELETE FROM leads WHERE id=?');$s->execute([$id]);flash('success','Lead deleted.');} redirect('leads.php');
