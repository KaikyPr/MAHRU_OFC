<?php
session_start();
require_once 'db/conexao.php';
require 'vendor/autoload.php'; // Para PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$novo_email = filter_input(INPUT_POST, 'novo_email', FILTER_VALIDATE_EMAIL);

if (!$novo_email) {
    echo json_encode(['success' => false, 'message' => 'Formato de e-mail inválido.']);
    exit;
}

// Buscar email atual do usuário
try {
    $stmt_atual = $pdo->prepare("SELECT email FROM usuarios WHERE id = :id");
    $stmt_atual->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt_atual->execute();
    $usuario_atual = $stmt_atual->fetch();

    if (!$usuario_atual) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
        exit;
    }
    $email_atual = $usuario_atual['email'];

    if ($novo_email === $email_atual) {
        echo json_encode(['success' => false, 'message' => 'O novo e-mail não pode ser igual ao atual.']);
        exit;
    }

    // Verificar se o novo e-mail já está em uso
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
    $stmt_check->bindParam(':email', $novo_email, PDO::PARAM_STR);
    $stmt_check->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado por outro usuário.']);
        exit;
    }

    // Gerar código de verificação e data de expiração
    $codigo = substr(str_shuffle("0123456789"), 0, 6);
    $expira = new DateTime();
    $expira->add(new DateInterval('PT15M')); // Expira em 15 minutos
    $expira_formatado = $expira->format('Y-m-d H:i:s');

    // Salvar informações no banco
    $stmt_update = $pdo->prepare("UPDATE usuarios SET new_email_pending = :new_email, email_change_code = :code, email_change_expires = :expires WHERE id = :id");
    $stmt_update->bindParam(':new_email', $novo_email, PDO::PARAM_STR);
    $stmt_update->bindParam(':code', $codigo, PDO::PARAM_STR);
    $stmt_update->bindParam(':expires', $expira_formatado, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt_update->execute();

    // Enviar e-mail de verificação para o EMAIL ATUAL
    $mail = new PHPMailer(true);
    try {
        // Configurações do servidor (ajustar conforme necessário - usar variáveis de ambiente é recomendado)
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Substituir pelo seu host SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'seu_email@example.com'; // Substituir pelo seu email SMTP
        $mail->Password   = 'sua_senha'; // Substituir pela sua senha SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Ou SMTPS
        $mail->Port       = 587; // Ou 465 para SMTPS

        // Remetente e Destinatário
        $mail->setFrom('no-reply@mahru.com', 'MAHRU - Sistema');
        $mail->addAddress($email_atual); // Enviar para o email ATUAL

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'MAHRU - Confirmação de Alteração de E-mail';
        $mail->Body    = "Olá,<br><br>Recebemos uma solicitação para alterar o e-mail da sua conta MAHRU para <b>{$novo_email}</b>.<br>" .
                         "Use o código a seguir para confirmar esta alteração. O código é válido por 15 minutos.<br><br>" .
                         "Seu código de verificação: <b>{$codigo}</b><br><br>" .
                         "Se você não solicitou esta alteração, pode ignorar este e-mail.<br><br>" .
                         "Atenciosamente,<br>Equipe MAHRU";
        $mail->AltBody = "Olá,\n\nRecebemos uma solicitação para alterar o e-mail da sua conta MAHRU para {$novo_email}.\n" .
                         "Use o código a seguir para confirmar esta alteração. O código é válido por 15 minutos.\n\n" .
                         "Seu código de verificação: {$codigo}\n\n" .
                         "Se você não solicitou esta alteração, pode ignorar este e-mail.\n\n" .
                         "Atenciosamente,\nEquipe MAHRU";

        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Um código de verificação foi enviado para o seu e-mail atual (' . $email_atual . ').']);

    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail de confirmação: {$mail->ErrorInfo}");
        // Reverter a atualização no banco em caso de falha no envio?
        // $stmt_revert = $pdo->prepare("UPDATE usuarios SET new_email_pending = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id");
        // $stmt_revert->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        // $stmt_revert->execute();
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar o e-mail de verificação. Tente novamente mais tarde.']);
    }

} catch (PDOException $e) {
    error_log("Erro de PDO em request_email_change: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor ao processar a solicitação.']);
}

?>
