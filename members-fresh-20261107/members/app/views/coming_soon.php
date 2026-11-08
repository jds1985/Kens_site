<h1>Degrees — Coming Soon</h1>
<?= coming_soon_box() ?>
<p class="actions"><?php if (current_user()): ?><a class="btn primary" href="<?= e(url('/dashboard')) ?>">Go to my page</a><?php else: ?><a class="btn primary" href="<?= e(url('/register')) ?>">Sign up</a> <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a><?php endif; ?></p>
