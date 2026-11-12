<?php
declare(strict_types=1);

/** Send a plain-text email. Driver set in config: mail | smtp | log. Returns true on success. */
function send_mail(string $to, string $subject, string $body): bool {
    $m = cfg('mail', []);
    $from = $m['from'] ?? 'members@localhost';
    $fromName = $m['from_name'] ?? 'Invisible Society';
    $driver = $m['driver'] ?? 'mail';
    if (preg_match('/[\r\n]/', $to . $subject . $from . $fromName)) return false;   // header injection guard

    if ($driver === 'log') {
        $line = "=== " . gmdate('c') . "\nTo: $to\nSubject: $subject\n\n$body\n\n";
        return file_put_contents(ROOT_DIR . '/storage/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }
    $headers = [
        'From' => mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>",
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'Date' => date('r'),
    ];
    $subj = mb_encode_mimeheader($subject, 'UTF-8');
    if ($driver === 'mail') {
        $h = '';
        foreach ($headers as $k => $v) $h .= "$k: $v\r\n";
        return @mail($to, $subj, $body, $h, '-f' . $from);
    }
    if ($driver === 'smtp') return smtp_send($m['smtp'] ?? [], $from, $to, $subj, $headers, $body);
    return false;
}

function smtp_send(array $c, string $from, string $to, string $subj, array $headers, string $body): bool {
    $secure = $c['secure'] ?? 'ssl';
    $host = ($secure === 'ssl' ? 'ssl://' : '') . $c['host'];
    $fp = @stream_socket_client($host . ':' . (int)$c['port'], $en, $es, 15);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $out = '';
        while (($l = fgets($fp, 515)) !== false) { $out .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $s, string $expect) use ($fp, $read) {
        fwrite($fp, $s . "\r\n");
        $r = $read();
        return strpos($r, $expect) === 0;
    };
    $read();
    $ok = $cmd('EHLO members', '250');
    if ($ok && $secure === 'tls') {
        $ok = $cmd('STARTTLS', '220') && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $ok = $ok && $cmd('EHLO members', '250');
    }
    // Empty user and password means no login. That is the local demo catcher.
    // A real mailbox sets both. SMTP stays unused while mail.driver is mail or log.
    $user = (string)($c['user'] ?? '');
    $pass = (string)($c['pass'] ?? '');
    if ($ok && ($user !== '' || $pass !== '')) {
        $ok = $cmd('AUTH LOGIN', '334') && $cmd(base64_encode($user), '334') && $cmd(base64_encode($pass), '235');
    }
    $ok = $ok && $cmd("MAIL FROM:<$from>", '250') && $cmd("RCPT TO:<$to>", '250') && $cmd('DATA', '354');
    if ($ok) {
        $h = "To: $to\r\nSubject: $subj\r\n";
        foreach ($headers as $k => $v) $h .= "$k: $v\r\n";
        $data = str_replace("\n.", "\n..", str_replace("\r\n", "\n", $body));
        $data = str_replace("\n", "\r\n", $data);
        fwrite($fp, $h . "\r\n" . $data . "\r\n.\r\n");
        $ok = strpos($read(), '250') === 0;
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}
