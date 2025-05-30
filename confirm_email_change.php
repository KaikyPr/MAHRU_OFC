<?php
session_start();
require_once 'db/conexao.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$codigo_verificacao = filter_input(INPUT_POST, 'codigo_verificacao', FILTER_SANITIZE_STRING);

if (empty($codigo_verificacao) || !ctype_digit($codigo_verificacao) || strlen($codigo_verificacao) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Código de verificação inválido.']);
    exit;
}

try {
    // Buscar informações da solicitação de mudança de e-mail
    $stmt = $pdo->prepare("SELECT new_email_pending, email_change_code, email_change_expires FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $solicitacao = $stmt->fetch();

    if (!$solicitacao || empty($solicitacao['email_change_code']) || empty($solicitacao['new_email_pending'])) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma solicitação de alteração de e-mail pendente encontrada ou dados inválidos.']);
        exit;
    }

    $novo_email_pendente = $solicitacao['new_email_pending'];
    $codigo_db = $solicitacao['email_change_code'];
    $expira_db = $solicitacao['email_change_expires'];

    // Verificar se o código coincide
    if ($codigo_verificacao !== $codigo_db) {
        echo json_encode(['success' => false, 'message' => 'Código de verificação incorreto.']);
        exit;
    }

    // Verificar se o código expirou
    $agora = new DateTime();
    $expira_dt = new DateTime($expira_db);

    if ($agora > $expira_dt) {
        // Limpar campos se expirou
        $stmt_clear = $pdo->prepare("UPDATE usuarios SET new_email_pending = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id");
        $stmt_clear->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt_clear->execute();
        echo json_encode(['success' => false, 'message' => 'Código de verificação expirado. Solicite a alteração novamente.']);
        exit;
    }

    // Verificar novamente se o novo e-mail já está em uso (caso raro, mas seguro)
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
    $stmt_check->bindParam(':email', $novo_email_pendente, PDO::PARAM_STR);
    $stmt_check->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    if ($stmt_check->fetch()) {
        // Limpar campos se o email foi pego por outro usuário nesse meio tempo
        $stmt_clear = $pdo->prepare("UPDATE usuarios SET new_email_pending = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id");
        $stmt_clear->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt_clear->execute();
        echo json_encode(['success' => false, 'message' => 'O novo e-mail foi registrado por outro usuário. Solicite a alteração novamente com um e-mail diferente.']);
        exit;
    }

    // Tudo certo, atualizar o e-mail e limpar os campos de verificação
    $stmt_update = $pdo->prepare("UPDATE usuarios SET email = :new_email, new_email_pending = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id");
    $stmt_update->bindParam(':new_email', $novo_email_pendente, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    
    if ($stmt_update->execute()) {
        // Atualizar o email na sessão também, se necessário
        $_SESSION['usuario_email'] = $novo_email_pendente; // Assumindo que 'usuario_email' é usado na sessão
        echo json_encode(['success' => true, 'message' => 'Seu e-mail foi atualizado com sucesso para ' . htmlspecialchars($novo_email_pendente) . '.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o e-mail no banco de dados.']);
    }

} catch (PDOException $e) {
    error_log("Erro de PDO em confirm_email_change: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor ao confirmar a alteração de e-mail.']);
}

?>
