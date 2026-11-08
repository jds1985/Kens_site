<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

security_headers();
session_boot();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && strpos($path, $base) === 0) $path = substr($path, strlen($base));
$path = '/' . trim($path, '/');
if ($path === '/index.php') $path = '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET' || $method === 'HEAD') {
        switch (true) {
            case $path === '/': c_home(); break;
            case $path === '/degrees': c_degrees(); break;
            case (bool)preg_match('#^/degree/(\d{1,2})$#', $path, $m): c_degree((int)$m[1]); break;
            case $path === '/register': c_register_form(); break;
            case $path === '/login': c_login_form(); break;
            case $path === '/verify': c_verify_form(); break;
            case $path === '/forgot': c_forgot_form(); break;
            case $path === '/reset': c_reset_form(); break;
            case $path === '/dashboard': c_dashboard(); break;
            case $path === '/account': c_account_form(); break;
            case (bool)preg_match('#^/lesson/(\d{1,9})$#', $path, $m): c_lesson((int)$m[1]); break;
            case (bool)preg_match('#^/lesson/(\d{1,9})/pdf$#', $path, $m): c_lesson_pdf((int)$m[1]); break;
            case $path === '/admin': c_admin_home(); break;
            case $path === '/admin/degrees': c_admin_degrees(); break;
            case (bool)preg_match('#^/admin/degree/(\d{1,2})$#', $path, $m): c_admin_degree_form((int)$m[1]); break;
            case $path === '/admin/lesson/new': c_admin_lesson_form(0, (int)($_GET['degree'] ?? 1)); break;
            case (bool)preg_match('#^/admin/lesson/(\d{1,9})$#', $path, $m): c_admin_lesson_form((int)$m[1], 0); break;
            case $path === '/admin/members': c_admin_members(); break;
            case (bool)preg_match('#^/admin/member/(\d{1,9})$#', $path, $m): c_admin_member((int)$m[1]); break;
            case $path === '/admin/log': c_admin_log(); break;
            case $path === '/facilitator': if (degrees_soon()) { render_coming_soon(); break; } c_facilitator(); break;
            case $path === '/admin/facilitators': c_admin_facilitators(); break;
            default: http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'That page does not exist.']);
        }
    } elseif ($method === 'POST') {
        switch ($path) {
            case '/register': c_register_post(); break;
            case '/login': c_login_post(); break;
            case '/logout': c_logout(); break;
            case '/verify': c_verify_post(); break;
            case '/resend': c_resend_post(); break;
            case '/forgot': c_forgot_post(); break;
            case '/reset': c_reset_post(); break;
            case '/account': c_account_post(); break;
            case '/admin/lesson': c_admin_lesson_post(); break;
            case '/facilitator': if (degrees_soon()) { render_coming_soon(); break; } c_facilitator_post(); break;
            case '/facilitator/confirm': if (degrees_soon()) { render_coming_soon(); break; } c_facilitator_confirm_post(); break;
            default:
                if (degrees_soon() && preg_match('#^/degree/\d{1,2}/#', $path)) { render_coming_soon(); break; }
                if (preg_match('#^/degree/(\d{1,2})/claim$#', $path, $m)) c_claim_post((int)$m[1]);
                elseif (preg_match('#^/degree/(\d{1,2})/unclaim$#', $path, $m)) c_unclaim_post((int)$m[1]);
                elseif (preg_match('#^/degree/(\d{1,2})/facilitator$#', $path, $m)) c_choose_facilitator_post((int)$m[1]);
                elseif (preg_match('#^/degree/(\d{1,2})/bclaim$#', $path, $m)) c_part_b_claim_post((int)$m[1]);
                elseif (preg_match('#^/degree/(\d{1,2})/bunclaim$#', $path, $m)) c_part_b_unclaim_post((int)$m[1]);
                elseif (preg_match('#^/admin/facilitator/(\d{1,9})$#', $path, $m)) c_admin_facilitator_post((int)$m[1]);
                elseif (preg_match('#^/admin/degree/(\d{1,2})$#', $path, $m)) c_admin_degree_post((int)$m[1]);
                elseif (preg_match('#^/admin/lesson/(\d{1,9})/delete$#', $path, $m)) c_admin_lesson_delete((int)$m[1]);
                elseif (preg_match('#^/admin/member/(\d{1,9})$#', $path, $m)) c_admin_member_post((int)$m[1]);
                else { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'That page does not exist.']); }
        }
    } else {
        http_response_code(405); echo 'Method not allowed';
    }
} catch (Throwable $ex) {
    error_log('members error: ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    while (ob_get_level() > 0) ob_end_clean();   // drop any half-built page
    if (!headers_sent()) http_response_code(500);
    if (cfg('debug')) { echo '<pre>' . e((string)$ex) . '</pre>'; exit; }
    try {
        render('error', ['title' => 'Something went wrong', 'message' => 'Sorry, something went wrong on our side. Please try again in a few minutes.']);
    } catch (Throwable $ex2) {
        // The database itself may be down: show a plain page that needs no database.
        echo '<!DOCTYPE html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Please try again later</title>'
           . '<body style="font:24px/1.6 Georgia,serif;max-width:40rem;margin:2rem auto;padding:0 1rem"><h1>Please try again later</h1>'
           . '<p>The members area is not working right now. Please try again in a few minutes.</p></body></html>';
    }
}
