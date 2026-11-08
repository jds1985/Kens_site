<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Members') ?> · Invisible Society Members</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(url('/assets/members.css')) ?>">
<script src="<?= e(url('/assets/members.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#main">Skip to the main content</a>

<header>
  <div class="ken-top">Website is always under construction. Best viewed on larger screens.</div>
  <div class="ken-white"><div class="ken-tint">
    <div class="ken-row">
      <div class="ken-col side">&nbsp;</div>
      <p class="ken-col c2 lower">
        <a class="red" href="https://vauqoppasampi.w3spaces.com/"><i><b>V</b>au <b>Q</b>oppa <b>S</b>ampi<span class="tm">™/©</span></i></a><br>
        a secret Greek-letter fraternity,<br>with a 6,000-year-old tradition,<br>associated with the<br>
        <b>I</b>nvisible <b>S</b>ociety<span class="tm">™/©</span>.
      </p>
      <p class="ken-col c2">
        The<br>
        <span class="k21"><b>I</b>nvisible <b>S</b>ociety<span class="tm13">™/©</span></span><br>
        <span class="red"><b>U</b></span>nincorporated <span class="red"><b>S</b></span>piritual <span class="red2"><b>A</b></span>ssociation<br>
        &nbsp;<br>
        an alchemystical fraternity,<br>granting <b>P</b>erpetual <b>I</b>nitiation<span class="tm">™/©</span><br>
        through the Degrees of the<br>QaBbaLáH Dá‘aTh Institute<span class="tm">™/©</span>.
      </p>
      <p class="ken-col c2 lower">
        <a class="red" href="https://qabbalahinstitute.w3spaces.com/"><i>QaBbaLáH Dá‘aTh Institute<span class="tm">™/©</span></i></a><br>
        a virtual, non-local, symbolic university,<br>granting Seminary degrees in<br>in a way that is unique to the<br>
        <b>I</b>nvisible <b>S</b>ociety<span class="tm">™/©</span>.
      </p>
      <div class="ken-col side">&nbsp;</div>
    </div>
  </div></div>
  <nav class="ken-nav" aria-label="Invisible Society site">
    <a href="<?= e(site_url('index.html#')) ?>"><i>Home</i></a>
    <span class="k21"><b>Members</b></span>
    <a href="<?= e(site_url('index.html#thematrix')) ?>"><i>The Matrix</i></a>
    <a href="<?= e(site_url('index.html#perpetualinitiation')) ?>"><i><b>P</b>erpetual <b>I</b>nitiation<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#prosperityinitiative')) ?>"><i><b>P</b>rosperity <b>I</b>nitiative<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#QQQ')) ?>"><i>QQQ<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#calendar')) ?>">Calendar</a>
    <a href="<?= e(site_url('index.html#vauqoppasampi')) ?>"><i><b>V</b>au <b>Q</b>oppa <b>S</b>ampi<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#therapeutae')) ?>"><i>Therapeutae</i></a>
    <a href="<?= e(site_url('index.html#alchemy')) ?>"><i>Alchemy</i></a>
    <a href="<?= e(site_url('index.html#abraham')) ?>"><i>Abraham</i></a>
    <a href="<?= e(site_url('index.html#assassins')) ?>"><i>Assassins</i></a>
    <a href="<?= e(site_url('index.html#copyright')) ?>"><i>Copyright</i></a>
    <a href="<?= e(site_url('index.html#qabbalahinstitute')) ?>"><i>QaBbaLáH Dá‘aTh Institute</i></a>
  </nav>
  <div class="ken-white"><div class="ken-tint ken-motto">
    The<br>
    <span class="k21"><b>I</b>nvisible <b>S</b>ociety<span class="tm13">™/©</span></span><br>
    <span class="red"><b>P</b></span>rivacy, <span class="red"><b>S</b></span>ecrecy, <span class="red"><b>I</b></span>nvisibility<span class="tm">™/©</span>
  </div></div>
<nav class="bar" aria-label="Members area">
    <span class="memline">Members area · private:</span>
    <a href="<?= e(site_url('/')) ?>"><b>Home</b></a>
    <a href="<?= e(url('/')) ?>" class="big">Members</a>
    <a href="<?= e(url('/degrees')) ?>"><i>The Degrees<?= degrees_soon() ? ' (coming soon)' : '' ?></i></a>
    <a href="<?= e(url('/degree/0')) ?>"><i>Naught Degree</i></a>
    <?php if ($user): ?>
      <a href="<?= e(url('/dashboard')) ?>"><i>My page</i></a>
      <a href="<?= e(url('/account')) ?>"><i>My account</i></a>
      <?php if (!degrees_soon() && (fac_row((int)$user['id']) || may_apply_facilitator($user))): ?><a href="<?= e(url('/facilitator')) ?>"><i>Facilitator</i></a><?php endif; ?>
      <?php if ($user['role'] === 'admin'): ?><a href="<?= e(url('/admin')) ?>"><b>Admin</b></a><?php endif; ?>
      <form class="inline" method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit" class="linklike">Sign out</button></form>
    <?php else: ?>
      <a href="<?= e(url('/login')) ?>"><i>Sign in</i></a>
      <a href="<?= e(url('/register')) ?>"><i>Sign up</i></a>
    <?php endif; ?>
  </nav>
  <div class="tools" role="group" aria-label="Reading options">
    <button type="button" id="t-smaller" aria-label="Make text smaller">A−</button>
    <button type="button" id="t-reset" aria-label="Normal text size">A</button>
    <button type="button" id="t-bigger" aria-label="Make text larger">A+</button>
    <button type="button" id="t-contrast" aria-pressed="false">High contrast</button>
  </div>
</header>

<main id="main" tabindex="-1">
  <div class="panel">
    <?php if ($flash): ?>
      <div class="notice <?= $flash[0] === 'err' ? 'err' : 'ok' ?>" role="<?= $flash[0] === 'err' ? 'alert' : 'status' ?>" tabindex="-1" id="flash"><?= e($flash[1]) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </div>
</main>

<footer>
  <nav class="ken-nav" aria-label="Invisible Society site, bottom">
    <a href="<?= e(site_url('index.html#')) ?>"><i>Home</i></a>
    <a href="<?= e(url('/')) ?>"><i><b>M</b>embers</i></a>
    <a href="<?= e(site_url('index.html#thematrix')) ?>"><i>The Matrix</i></a>
    <a href="<?= e(site_url('index.html#perpetualinitiation')) ?>"><i><b>P</b>erpetual <b>I</b>nitiation<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#prosperityinitiative')) ?>"><i><b>P</b>rosperity <b>I</b>nitiative<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#QQQ')) ?>"><i>QQQ<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#calendar')) ?>">Calendar</a>
    <a href="<?= e(site_url('index.html#vauqoppasampi')) ?>"><i><b>V</b>au <b>Q</b>oppa <b>S</b>ampi<span class="tm">™/©</span></i></a>
    <a href="<?= e(site_url('index.html#therapeutae')) ?>"><i>Therapeutae</i></a>
    <a href="<?= e(site_url('index.html#alchemy')) ?>"><i>Alchemy</i></a>
    <a href="<?= e(site_url('index.html#abraham')) ?>"><i>Abraham</i></a>
    <a href="<?= e(site_url('index.html#assassins')) ?>"><i>Assassins</i></a>
    <a href="<?= e(site_url('index.html#copyright')) ?>"><i>Copyright</i></a>
    <a href="<?= e(site_url('index.html#qabbalahinstitute')) ?>"><i>QaBbaLáH Dá‘aTh Institute</i></a>
  </nav>
  <div class="ken-white"><div class="ken-tint">
    <div class="ken-row">
      <p class="ken-col c4"><span class="red2"><b>Notice:</b></span><br>Payment of membership and tuition fees<br>constitutes a<br><b>Non-Disclosure Agreement</b><br>with the <b>I</b>nvisible <b>S</b>ociety©<br>concerning teaching materials<br>and membership.</p>
      <p class="ken-col c4"><span class="red2"><b>Notice:</b></span><br>The numerous materials<br>of the <b>I</b>nvisible <b>S</b>ociety©<br>were created from 1980 to the present.<br>These materials are <b>copyrighted</b> and<br>their use is <b>licensed</b>.<br>©<br>Copyright <?= date('Y') ?> <b>I</b>nvisible <b>S</b>ociety<span class="tm">™/©</span><br>∴Bro. Ken, 32º <b><span class="blue">F</span><span class="red">M</span></b> 33º <b><span class="red">M</span><span class="blue">F</span></b>∴<br><b>I</b>nvisible <b>S</b>ecretary of the <b>I</b>nvisible <b>S</b>ociety<span class="tm">™/©</span><br><span class="red2"><b>U</b></span>nincorporated <span class="red2"><b>S</b></span>piritual <span class="red2"><b>A</b></span>ssociation<span class="tm">™</span></p>
      <p class="ken-col c4"><span class="red2"><b>Notice:</b></span><br>The numerous materials<br>Teaching materials are not for sale;<br>they are on long-term loan.<br>Tuition fees are for educational and<br>ministerial purposes.</p>
    </div>
  </div></div>
  <div class="ken-black"><span class="k21">Sacred Geometry</span><br>This website was made with W3schools Spaces.</div>
  <div class="ken-white"><div class="ken-tint ken-etc">
    &nbsp;<br>Matthew 7:13<br>διὰ τῆς στενῆς πύλης<br>through the stenotic pylon<br>The Narrow Gate<br>
    <span class="k21"><i>The Pi Arch</i></span><br>
    <img src="<?= e(url('/assets/ken/PiArchGreen.png')) ?>" width="55" height="89" alt="The Pi Arch"><br>
    Copyright © 1980<br>∴Bro. Ken, 32º <b><span class="blue">F</span><span class="red">M</span></b> 33º <b><span class="red">M</span><span class="blue">F</span></b>∴<br>
    <i>The Pi Arch</i> is our portal to higher consciousness.<br><br>
    "<i>Geometrically-trued Diagramming</i> creates a multi-dimensional wave in space, which can influence one's state of consciousness (just as these waves influence matter on all dimensions)."<br>
    ~ Marie Bauer Hall<br>(wife of Manly Palmer Hall, 33º)<br><i>God As Mother</i><br>Victoria Jennings (2002)<br><br>
    the net = τὸ δίκτθον = <b>1224</b> = ίχθύεϛ = fishes<br>
    <img src="<?= e(url('/assets/ken/TheNet.png')) ?>" width="138" height="138" alt="The Net"><br>
    <span class="k21"><b>I</b>nvisible <b>S</b>ociety<span class="tm13">™/©</span></span><br>
    <span class="red"><b>U</b></span>nincorporated <span class="red"><b>S</b></span>piritual <span class="red2"><b>A</b></span>ssociation<br><br>
  </div></div>
</footer>
</body>
</html>
