<?php
/**
 * RCS HRMS Pro - Notification Class
 * Handles SMS, Email, and WhatsApp notifications
 * 
 * Free SMS: Using Fast2SMS / TextLocal / MSG91 APIs
 * Email: Using PHPMailer / SendGrid
 * WhatsApp: Using WhatsApp Web QR Scan (Free) or Business API
 */

// Constant to avoid string duplication
define('REGEX_NON_NUMERIC', '/[^0-9]/');

class Notification {
    private $db;
    private $smsApiKey;
    private $emailConfig;
    private $whatsappConfig;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Get settings from database
        $settings = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'notif_%'"
        );
        
        foreach ($settings as $s) {
            switch ($s['setting_key']) {
                case 'notif_sms_api_key':
                    $this->smsApiKey = $s['setting_value'];
                    break;
                case 'notif_sms_provider':
                    $this->smsProvider = $s['setting_value'];
                    break;
                case 'notif_email_host':
                    $this->emailConfig['host'] = $s['setting_value'];
                    break;
                case 'notif_email_user':
                    $this->emailConfig['user'] = $s['setting_value'];
                    break;
                case 'notif_email_pass':
                    $this->emailConfig['pass'] = $s['setting_value'];
                    break;
                default:
                    // Ignore unknown settings
                    break;
            }
        }
    }
    
    // ============================================
    // SMS Methods (Free providers)
    // ============================================
    
    /**
     * Send SMS using Fast2SMS (Free tier available)
     * Get API key from: https://docs.fast2sms.com
     */
    public function sendSMS($mobile, $message, $templateId = null) {
        // Clean mobile number
        $mobile = preg_replace(REGEX_NON_NUMERIC, '', $mobile);
        
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile;
        }
        
        // Fast2SMS API
        $apiKey = $this->smsApiKey ?? 'YOUR_FAST2SMS_API_KEY';
        
        $url = "https://www.fast2sms.com/dev/bulkV2";
        
        $data = [
            'route' => 'q', // Quick transactional route
            'message' => $message,
            'language' => 'english',
            'flash' => 0,
            'numbers' => substr($mobile, 2) // Remove country code for Fast2SMS
        ];
        
        if ($templateId) {
            $data['route'] = 'dlt'; // DLT route
            $data['template_id'] = $templateId;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authorization: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log the SMS
        $this->logNotification('sms', $mobile, $message, $httpCode == 200 ? 'sent' : 'failed', $response);
        
        return [
            'success' => $httpCode == 200 && ($result['return'] ?? false),
            'message' => $result['message'] ?? 'SMS sent',
            'response' => $result
        ];
    }
    
    /**
     * Send SMS using TextLocal (Free trial)
     * Get API key from: https://api.textlocal.in
     */
    public function sendSMSTextLocal($mobile, $message) {
        $apiKey = $this->smsApiKey ?? 'YOUR_TEXTLOCAL_API_KEY';
        
        $mobile = preg_replace(REGEX_NON_NUMERIC, '', $mobile);
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile;
        }
        
        $url = "https://api.textlocal.in/send/";
        
        $data = [
            'apikey' => $apiKey,
            'numbers' => $mobile,
            'message' => urlencode($message),
            'sender' => 'TXTLCL' // Replace with approved sender ID
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        $this->logNotification('sms', $mobile, $message, $result['status'] == 'success' ? 'sent' : 'failed', $response);
        
        return [
            'success' => $result['status'] == 'success',
            'message' => 'SMS sent via TextLocal',
            'response' => $result
        ];
    }
    
    // ============================================
    // Email Methods
    // ============================================
    
    /**
     * Send Email using PHP's mail() or SMTP
     */
    public function sendEmail($to, $subject, $body, $attachments = [], $isHTML = true) {
        $fromEmail = $this->emailConfig['user'] ?? 'noreply@rcshrms.com';
        $fromName = 'RCS HRMS Pro';
        
        // Headers
        $headers = [
            'From' => $fromName . ' <' . $fromEmail . '>',
            'Reply-To' => $fromEmail,
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        if ($isHTML) {
            $headers['MIME-Version'] = '1.0';
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }
        
        // Convert headers array to string
        $headerStr = '';
        foreach ($headers as $key => $value) {
            $headerStr .= $key . ': ' . $value . "\r\n";
        }
        
        // Handle attachments (simple implementation)
        if (!empty($attachments)) {
            $boundary = md5(time());
            $headerStr = "MIME-Version: 1.0\r\n";
            $headerStr .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $body . "\r\n";
            
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $content = chunk_split(base64_encode(file_get_contents($attachment['path'])));
                    $body .= "--$boundary\r\n";
                    $body .= "Content-Type: " . ($attachment['type'] ?? 'application/octet-stream') . "; name=\"" . $attachment['name'] . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\n\r\n";
                    $body .= $content . "\r\n";
                }
            }
            $body .= "--$boundary--";
        }
        
        // Send email
        $result = mail($to, $subject, $body, $headerStr);
        
        // Log
        $this->logNotification('email', $to, $subject, $result ? 'sent' : 'failed', '');
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email'
        ];
    }
    
    /**
     * Send email using Gmail SMTP (more reliable)
     */
    public function sendEmailSMTP($to, $subject, $body, $attachments = []) {
        // Check if PHPMailer is available
        $phpmailerPath = APP_ROOT . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        
        if (!file_exists($phpmailerPath)) {
            // Fall back to basic mail
            return $this->sendEmail($to, $subject, $body, $attachments);
        }
        
        require_once $phpmailerPath;
        require_once APP_ROOT . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $this->emailConfig['host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->emailConfig['user'] ?? 'your-email@gmail.com';
            $mail->Password = $this->emailConfig['pass'] ?? 'your-app-password';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            $mail->setFrom($this->emailConfig['user'] ?? 'noreply@rcshrms.com', 'RCS HRMS Pro');
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }
            }
            
            $mail->send();
            
            $this->logNotification('email', $to, $subject, 'sent', '');
            
            return ['success' => true, 'message' => 'Email sent via SMTP'];
            
        } catch (Exception $e) {
            $this->logNotification('email', $to, $subject, 'failed', $e->getMessage());
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // WhatsApp Methods (Free via QR Scan)
    // ============================================
    
    /**
     * Generate WhatsApp link (Free - opens WhatsApp Web/App)
     * User can scan QR or click link to send message
     */
    public function generateWhatsAppLink($mobile, $message) {
        $mobile = preg_replace(REGEX_NON_NUMERIC, '', $mobile);
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile;
        }
        
        return [
            'link' => "https://wa.me/{$mobile}?text=" . urlencode($message),
            'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode("https://wa.me/{$mobile}?text=" . urlencode($message)),
            'instructions' => 'Click link or scan QR code to send message via WhatsApp'
        ];
    }
    
    /**
     * Send WhatsApp message using WhatsApp Business API (if configured)
     * Or return link for manual sending
     */
    public function sendWhatsApp($mobile, $message, $autoSend = false) {
        $mobile = preg_replace(REGEX_NON_NUMERIC, '', $mobile);
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile;
        }
        
        if ($autoSend && !empty($this->whatsappConfig['api_url'])) {
            // Use WhatsApp Business API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->whatsappConfig['api_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'phone' => $mobile,
                'message' => $message
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . ($this->whatsappConfig['api_token'] ?? '')
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $this->logNotification('whatsapp', $mobile, $message, 'sent', $response);
            
            return ['success' => true, 'message' => 'WhatsApp sent via API'];
        }
        
        // Return link for manual sending (Free method)
        $link = $this->generateWhatsAppLink($mobile, $message);
        
        $this->logNotification('whatsapp', $mobile, $message, 'link_generated', json_encode($link));
        
        return [
            'success' => true,
            'manual' => true,
            'link' => $link['link'],
            'qr_code' => $link['qr_code'],
            'message' => 'WhatsApp link generated. User can click or scan QR to send.'
        ];
    }
    
    // ============================================
    // Bulk Notifications
    // ============================================
    
    /**
     * Send bulk SMS
     */
    public function sendBulkSMS($recipients, $message) {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $mobile = is_array($recipient) ? ($recipient['mobile'] ?? $recipient['phone']) : $recipient;
            $results[] = [
                'mobile' => $mobile,
                'result' => $this->sendSMS($mobile, $message)
            ];
        }
        
        return $results;
    }
    
    /**
     * Send payslip via email
     */
    public function sendPayslipEmail($employeeId, $payrollId) {
        // Get employee and payroll details
        $data = $this->db->fetch(
            "SELECT e.full_name, e.personal_email, e.official_email, e.employee_code,
                    p.*, pp.month, pp.year
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             JOIN payroll_periods pp ON p.payroll_period_id = pp.id
             WHERE e.id = :eid AND p.id = :pid",
            ['eid' => $employeeId, 'pid' => $payrollId]
        );
        
        if (!$data) {
            return ['success' => false, 'message' => 'Data not found'];
        }
        
        $email = $data['personal_email'] ?: $data['official_email'];
        
        if (!$email) {
            return ['success' => false, 'message' => 'No email address found'];
        }
        
        // Generate email body
        $monthYear = date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year']));
        
        $body = $this->getEmailTemplate('payslip', [
            'employee_name' => $data['full_name'],
            'employee_code' => $data['employee_code'],
            'month_year' => $monthYear,
            'net_pay' => formatCurrency($data['net_salary']),
            'gross' => formatCurrency($data['gross_earnings'] ?? $data['basic'] * 1.4),
            'deductions' => formatCurrency($data['total_deductions'] ?? 0),
            'company_name' => 'RCS TRUE FACILITIES PVT LTD'
        ]);
        
        return $this->sendEmail(
            $email,
            "Payslip for {$monthYear} - RCS HRMS",
            $body
        );
    }
    
    // ============================================
    // Notification Templates
    // ============================================
    
    public function getEmailTemplate($type, $data) {
        $templates = [
            'payslip' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: #4e73df; color: white; padding: 20px; text-align: center;">
                        <h2 style="margin: 0;">Payslip Notification</h2>
                    </div>
                    <div style="padding: 20px; border: 1px solid #ddd;">
                        <p>Dear <strong>{{employee_name}}</strong>,</p>
                        <p>Your payslip for <strong>{{month_year}}</strong> has been processed.</p>
                        
                        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                            <tr style="background: #f8f9fc;">
                                <td style="padding: 10px; border: 1px solid #ddd;">Employee Code</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">{{employee_code}}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;">Gross Earnings</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">{{gross}}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;">Total Deductions</td>
                                <td style="padding: 10px; border: 1px solid #ddd;">{{deductions}}</td>
                            </tr>
                            <tr style="background: #e8f5e9;">
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Net Pay</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{{net_pay}}</strong></td>
                            </tr>
                        </table>
                        
                        <p>Please login to the HRMS portal to view/download your detailed payslip.</p>
                        
                        <p>Best regards,<br>{{company_name}}</p>
                    </div>
                </div>
            ',
            
            'salary_credit' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: #1cc88a; color: white; padding: 20px; text-align: center;">
                        <h2 style="margin: 0;">💰 Salary Credited!</h2>
                    </div>
                    <div style="padding: 20px; border: 1px solid #ddd;">
                        <p>Dear <strong>{{employee_name}}</strong>,</p>
                        <p>Your salary for <strong>{{month_year}}</strong> has been credited to your bank account.</p>
                        <p><strong>Amount: {{net_pay}}</strong></p>
                        <p>Thank you for your hard work and dedication!</p>
                        <p>Best regards,<br>{{company_name}}</p>
                    </div>
                </div>
            ',
            
            'leave_approval' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: #36b9cc; color: white; padding: 20px; text-align: center;">
                        <h2 style="margin: 0;">Leave Application Update</h2>
                    </div>
                    <div style="padding: 20px; border: 1px solid #ddd;">
                        <p>Dear <strong>{{employee_name}}</strong>,</p>
                        <p>Your leave application for <strong>{{leave_dates}}</strong> has been <strong>{{status}}</strong>.</p>
                        <p><strong>Reason:</strong> {{leave_reason}}</p>
                        <p>Best regards,<br>{{company_name}}</p>
                    </div>
                </div>
            '
        ];
        
        $template = $templates[$type] ?? '';
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    public function getSMSTemplate($type, $data) {
        $templates = [
            'salary_credit' => 'Dear {name}, your salary of Rs. {amount} for {month} has been credited to your account. - RCS HRMS',
            'leave_approval' => 'Dear {name}, your leave for {dates} has been {status}. - RCS HRMS',
            'attendance_alert' => 'Dear {name}, you are marked absent today. Please contact HR if this is incorrect. - RCS HRMS',
            'pf_update' => 'Dear {name}, your PF contribution for {month} is Rs. {amount}. UAN: {uan} - RCS HRMS'
        ];
        
        $template = $templates[$type] ?? '';
        
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    // ============================================
    // Logging
    // ============================================
    
    private function logNotification($type, $recipient, $message, $status, $response) {
        try {
            $this->db->insert('notification_logs', [
                'type' => $type,
                'recipient' => $recipient,
                'message' => substr($message, 0, 500),
                'status' => $status,
                'response' => substr($response, 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id'] ?? null
            ]);
        } catch (Exception $e) {
            // Log error silently
            error_log('Notification log error: ' . $e->getMessage());
        }
    }
    
    // ============================================
    // Dashboard Alerts
    // ============================================
    
    public function getDashboardAlerts() {
        $alerts = [];
        
        // Check for pending compliance
        $pendingCompliance = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM compliance_filings WHERE status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        
        if ($pendingCompliance > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'title' => 'Compliance Pending',
                'message' => "$pendingCompliance compliance filings due within 7 days",
                'link' => 'index.php?page=compliance/dashboard'
            ];
        }
        
        // Check for pending salary below minimum wage
        $belowMinWage = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM employees e
             LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id
             LEFT JOIN minimum_wages mw ON e.state = mw.state
             WHERE e.status = 'approved' AND ess.gross_salary < mw.total_per_month"
        );
        
        if ($belowMinWage > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'exclamation-circle',
                'title' => 'Minimum Wage Violation',
                'message' => "$belowMinWage employees paid below minimum wage",
                'link' => 'index.php?page=compliance/minimum-wage-check'
            ];
        }
        
        // Check for pending F&F settlements
        $pendingFF = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM employee_settlements WHERE status = 'pending'"
        );
        
        if ($pendingFF > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'cash-coin',
                'title' => 'Pending Settlements',
                'message' => "$pendingFF F&F settlements pending approval",
                'link' => 'index.php?page=settlement/list'
            ];
        }
        
        // Check for pending approvals
        $pendingApprovals = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM employees WHERE status LIKE 'pending%'"
        );
        
        if ($pendingApprovals > 0) {
            $alerts[] = [
                'type' => 'primary',
                'icon' => 'person-plus',
                'title' => 'Pending Approvals',
                'message' => "$pendingApprovals employees pending approval",
                'link' => 'index.php?page=employee/list&status=pending'
            ];
        }
        
        return $alerts;
    }
}
?>
