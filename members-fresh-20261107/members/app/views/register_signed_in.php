<h1>Sign up</h1>
<div class="notice ok" role="status">You are already signed in as <b><?= e($u['pseudonym']) ?></b><?= !empty($u['member_id']) ? ' (Member ID <b>' . e($u['member_id']) . '</b>)' : '' ?>. You do not need to sign up again.</div>
<p class="actions"><a class="btn primary" href="<?= e(url('/dashboard')) ?>">Go to my page</a></p>
<p>Want to make a new account for someone else? Sign out first, then press Sign up.</p>
<form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><input type="hidden" name="next" value="register"><button class="btn" type="submit">Sign out, then sign up</button></form>
