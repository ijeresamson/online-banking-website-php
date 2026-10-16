<?php 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

 try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM, 'MidwesternBank Security');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'New Login to Your MidwesternBank Account';
            $mail->Body    = '
                <div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;
                            background:#ffffff;border-radius:12px;overflow:hidden;
                            border:1px solid #e2e8f0;">

                    <!-- Header -->
                    <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);
                                padding:30px;text-align:center;">
                        <h2 style="color:#ffffff;margin:0;font-size:20px;font-weight:700;">
                            MidwesternBank
                        </h2>
                        <p style="color:rgba(255,255,255,0.7);font-size:13px;margin:6px 0 0;">
                            Security Notification
                        </p>
                    </div>

                    <!-- Body -->
                    <div style="padding:32px;">
                        <p style="margin:0 0 16px;color:#1a202c;font-size:15px;">
                            Hello <strong>' . htmlspecialchars($user['first_name']) . '</strong>,
                        </p>
                        <p style="color:#4a5568;font-size:14px;line-height:1.6;margin:0 0 24px;">
                            A new login was detected on your MidwesternBank account.
                            If this was you, no action is needed. If you did not sign in,
                            please contact us immediately.
                        </p>

                        <!-- Details Table -->
                        <table style="width:100%;border-collapse:collapse;background:#f7fafc;
                                      border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="padding:12px 16px;font-size:13px;font-weight:600;
                                           color:#2d3748;border-bottom:1px solid #e2e8f0;width:40%;">
                                    Date &amp; Time
                                </td>
                                <td style="padding:12px 16px;font-size:13px;color:#4a5568;
                                           border-bottom:1px solid #e2e8f0;">
                                    ' . date('d F Y, H:i:s T') . '
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:13px;font-weight:600;
                                           color:#2d3748;border-bottom:1px solid #e2e8f0;">
                                    IP Address
                                </td>
                                <td style="padding:12px 16px;font-size:13px;color:#4a5568;
                                           border-bottom:1px solid #e2e8f0;">
                                    ' . htmlspecialchars($ip) . '
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:13px;font-weight:600;
                                           color:#2d3748;">
                                    Account
                                </td>
                                <td style="padding:12px 16px;font-size:13px;color:#4a5568;">
                                    ' . htmlspecialchars($user['email']) . '
                                </td>
                            </tr>
                        </table>

                        <!-- Warning -->
                        <div style="margin-top:24px;padding:16px;background:#fff5f5;
                                    border-left:4px solid #e53e3e;border-radius:4px;">
                            <p style="margin:0;color:#c53030;font-size:13px;font-weight:600;">
                                Not you? Act immediately.
                            </p>
                            <p style="margin:6px 0 0;color:#742a2a;font-size:13px;">
                                Change your password and contact us at
                                <a href="mailto:security@MidwesternBank.com"
                                   style="color:#c53030;">
                                    security@MidwesternBank.com
                                </a>
                                or call <strong>1-800-MidwesternBank</strong>.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div style="padding:20px 32px;background:#f7fafc;
                                border-top:1px solid #e2e8f0;text-align:center;">
                        <p style="margin:0;font-size:11px;color:#a0aec0;">
                            &copy; 2026 MidwesternBank Financial Inc. Member FDIC.
                            This is an automated security alert. Do not reply to this email.
                        </p>
                    </div>
                </div>
            ';
            $mail->send();
        } catch(Exception $e) {
            // Mail failed — silently log and continue
            // A mail failure must never block a valid login
            error_log('Login alert email failed: ' . $e->getMessage());
        }
