<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Verificar se o PHPMailer está instalado
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Se não existir o autoload, verificar se os arquivos do PHPMailer existem diretamente
    if (!file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        // Criar pasta vendor se não existir
        if (!is_dir(__DIR__ . '/vendor')) {
            mkdir(__DIR__ . '/vendor', 0755, true);
        }
        if (!is_dir(__DIR__ . '/vendor/phpmailer')) {
            mkdir(__DIR__ . '/vendor/phpmailer', 0755, true);
        }
        if (!is_dir(__DIR__ . '/vendor/phpmailer/phpmailer')) {
            mkdir(__DIR__ . '/vendor/phpmailer/phpmailer', 0755, true);
        }
        if (!is_dir(__DIR__ . '/vendor/phpmailer/phpmailer/src')) {
            mkdir(__DIR__ . '/vendor/phpmailer/phpmailer/src', 0755, true);
        }
        
        // Definir constantes para evitar erros
        define('USE_PHPMAILER', false);
    } else {
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        
        define('USE_PHPMAILER', true);
    }
} else {
    require __DIR__ . '/vendor/autoload.php';
    
    define('USE_PHPMAILER', true);
}

// Definir constantes SMTP
define("SMTP_HOST", "mail.simonatosys.com.br");
define("SMTP_USERNAME", "mahru@simonatosys.com.br");
define("SMTP_PASSWORD", "eNbO.Gp)8}lM");
define("SMTP_PORT", 465);
define("SMTP_FROM_EMAIL", "mahru@simonatosys.com.br");
define("SMTP_FROM_NAME", "MAHRU Sistema");

require_once("db/conexao.php");

header("Content-Type: application/json");

$response = ["success" => false, "message" => "Erro desconhecido."];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"])) {
    $email = filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $response["message"] = "Formato de email inválido.";
        echo json_encode($response);
        exit;
    }

    try {
        // Modificado para buscar também o nome do usuário
        $sql_check = "SELECT id, nome FROM usuarios WHERE email = :email LIMIT 1";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt_check->execute();

        if ($stmt_check->rowCount() > 0) {
            $usuario = $stmt_check->fetch();
            $user_id = $usuario["id"];
            $user_nome = isset($usuario["nome"]) ? $usuario["nome"] : "";

            $reset_code = random_int(100000, 999999);
            $expires_in_minutes = 15;
            $reset_expires = date("Y-m-d H:i:s", strtotime("+" . $expires_in_minutes . " minutes"));

            $sql_update = "UPDATE usuarios SET reset_code = :code, reset_expires = :expires WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindValue(":code", $reset_code, PDO::PARAM_STR);
            $stmt_update->bindValue(":expires", $reset_expires, PDO::PARAM_STR);
            $stmt_update->bindValue(":id", $user_id, PDO::PARAM_INT);

            if ($stmt_update->execute()) {
                $response["success"] = true;

                // Personalizar a saudação com o nome do usuário
                $saudacao = empty($user_nome) ? "Olá" : "Olá, " . $user_nome;
                
                $subject = "Seu Código de Recuperação de Senha - MAHRU";
                
                // Template HTML bonito para o email com cores padronizadas
                $logo_url = "https://simonatosys.com.br/MAHRU/img/LOGO.png"; // Ajuste para o caminho correto da logo
                
                // Cores padronizadas do MAHRU
                $primary_color = "#00aaff";      // Azul principal
                $secondary_color = "#121212";    // Fundo escuro
                $text_color = "#ffffff";         // Texto claro
                $accent_color = "#0088cc";       // Azul mais escuro para hover
                $neutral_color = "#2a2a2a";      // Cinza escuro para cards
                $light_text = "#cccccc";         // Texto cinza claro
                
                $message_html = '
                <!DOCTYPE html>
                <html lang="pt-br">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Recuperação de Senha</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            color: ' . $text_color . ';
                            max-width: 600px;
                            margin: 0 auto;
                            padding: 20px;
                            background-color: ' . $secondary_color . ';
                        }
                        .email-container {
                            border: 1px solid ' . $neutral_color . ';
                            border-radius: 5px;
                            padding: 20px;
                            background-color: ' . $secondary_color . ';
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 20px;
                            padding: 10px;
                            background-color: ' . $secondary_color . ';
                            border-radius: 5px 5px 0 0;
                        }
                        .logo {
                            max-width: 150px;
                            height: auto;
                        }
                        .greeting {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 15px;
                            color: ' . $primary_color . ';
                        }
                        .message {
                            margin-bottom: 20px;
                            color: ' . $text_color . ';
                        }
                        .code-container {
                            background-color: ' . $primary_color . ';
                            color: ' . $text_color . ';
                            padding: 15px;
                            text-align: center;
                            border-radius: 5px;
                            margin: 20px 0;
                            font-size: 24px;
                            font-weight: bold;
                            letter-spacing: 2px;
                        }
                        .copy-button {
                            background-color: ' . $primary_color . ';
                            color: ' . $text_color . ';
                            border: none;
                            padding: 10px 20px;
                            text-align: center;
                            text-decoration: none;
                            display: inline-block;
                            font-size: 16px;
                            margin: 10px 0;
                            cursor: pointer;
                            border-radius: 5px;
                        }
                        .copy-button:hover {
                            background-color: ' . $accent_color . ';
                        }
                        .expiry {
                            font-style: italic;
                            color: ' . $light_text . ';
                            margin-bottom: 20px;
                        }
                        .footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: ' . $light_text . ';
                            border-top: 1px solid ' . $neutral_color . ';
                            padding-top: 10px;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="header">
                            <img src="' . $logo_url . '" alt="MAHRU Logo" class="logo">
                        </div>
                        <div class="greeting">' . $saudacao . ',</div>
                        <div class="message">
                            <p>Você solicitou a recuperação de senha para sua conta no sistema MAHRU.</p>
                            <p>Utilize o código abaixo para redefinir sua senha:</p>
                        </div>
                        <div class="code-container" id="code">' . $reset_code . '</div>
                        <div class="expiry">
                            <p>Este código expirará em ' . $expires_in_minutes . ' minutos.</p>
                            <p>Se você não solicitou esta recuperação de senha, ignore este email.</p>
                        </div>
                        <div class="footer">
                            <p>Atenciosamente,<br>Equipe MAHRU<br>powered by Simonatosys</p>
                        </div>
                    </div>
                </body>
                </html>
                ';
                
                // Versão em texto simples para clientes que não suportam HTML
                $message_text = "$saudacao,\n\nVocê solicitou a recuperação de senha para sua conta no MAHRU.\n\nSeu código de recuperação é: " . $reset_code . "\n\nEste código expirará em " . $expires_in_minutes . " minutos.\n\nSe você não solicitou isso, ignore este email.\n\nAtenciosamente,\nEquipe MAHRU\npowered by Simonatosys";

                $email_sent_successfully = false;
                
                // Tentar enviar via PHPMailer se disponível
                if (defined("USE_PHPMAILER") && USE_PHPMAILER === true) {
                    $mail = new PHPMailer(true);
                    try {
                        // Configurações do servidor
                        $mail->SMTPDebug = 2; // Ativar debug detalhado
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USERNAME;
                        $mail->Password   = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = SMTP_PORT;
                        $mail->CharSet    = "UTF-8";
                        
                        // Desativar verificação SSL
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        
                        // Remetente e destinatário
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email);
                        
                        // Conteúdo
                        $mail->isHTML(true); // Definir como HTML
                        $mail->Subject = $subject;
                        $mail->Body    = $message_html; // Versão HTML
                        $mail->AltBody = $message_text; // Versão texto para clientes que não suportam HTML
                        
                        // Enviar email
                        $mail->send();
                        $email_sent_successfully = true;
                        $response["message"] = "Um código de recuperação foi enviado para seu email via SMTP. Ele expira em " . $expires_in_minutes . " minutos.";
                    } catch (Exception $e) {
                        // Registrar erro detalhado
                        error_log("PHPMailer Error: {$mail->ErrorInfo}");
                        $response["message"] = "Erro ao enviar email: " . $mail->ErrorInfo;
                        $response["debug_info"] = $mail->ErrorInfo;
                        $response["code"] = $reset_code; // Incluir código para debug
                    }
                }

                // Fallback para função mail() nativa do PHP
                if (!$email_sent_successfully) {
                    // Cabeçalhos para email HTML
                    $headers = "From: " . SMTP_FROM_EMAIL . "\r\n" .
                               "Reply-To: " . SMTP_FROM_EMAIL . "\r\n" .
                               "MIME-Version: 1.0\r\n" .
                               "Content-Type: text/html; charset=UTF-8\r\n" .
                               "X-Mailer: PHP/" . phpversion();

                    if (@mail($email, $subject, $message_html, $headers)) {
                        $response["message"] = "Um código de recuperação foi enviado para seu email (via mail()). Verifique spam. Ele expira em " . $expires_in_minutes . " minutos.";
                    } else {
                        error_log("Falha ao enviar email de recuperação (via mail()) para: " . $email . " Código: " . $reset_code);
                        $response["message"] = "Código gerado com sucesso, mas houve um erro ao enviar o email. Verifique as configurações do servidor.";
                        $response["code"] = $reset_code; // Incluir código para debug
                    }
                }

            } else {
                $response["message"] = "Erro ao salvar o código de recuperação no banco de dados.";
                $response["success"] = false;
            }

        } else {
            $response["message"] = "Email não encontrado em nossa base de dados.";
        }

    } catch (PDOException $e) {
        error_log("Erro PDO em ajax_request_reset: " . $e->getMessage());
        $response["message"] = "Erro no banco de dados ao processar a solicitação.";
    } catch (Exception $e) {
        error_log("Erro geral em ajax_request_reset: " . $e->getMessage());
        $response["message"] = "Ocorreu um erro inesperado (" . get_class($e) . ").";
    }

} else {
    $response["message"] = "Requisição inválida.";
}

echo json_encode($response);
?>
