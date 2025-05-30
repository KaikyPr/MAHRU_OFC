<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php");
// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require "vendor/phpmailer/phpmailer/src/Exception.php";
require "vendor/phpmailer/phpmailer/src/PHPMailer.php";
require "vendor/phpmailer/phpmailer/src/SMTP.php";

// Obter ID do usuário da sessão
$usuario_id = $_SESSION["usuario_id"];

// Mensagens de erro/sucesso
$mensagem = "";
$tipo_mensagem = ""; // success, error, warning, info

// Controle de seção ativa (usado para expandir a seção correta)
$secao_ativa = isset($_GET['secao']) ? $_GET['secao'] : 'perfil';
if (!in_array($secao_ativa, ['perfil', 'email', 'avatar', 'senha'])) {
    $secao_ativa = 'perfil';
}

// Controle de sub-etapa para o email (usado para o fluxo de alteração de email)
$sub_etapa_email = isset($_GET['sub_etapa']) ? intval($_GET['sub_etapa']) : 1;
if ($sub_etapa_email < 1 || $sub_etapa_email > 3) {
    $sub_etapa_email = 1; // 1 = Solicitar código, 2 = Verificar código e inserir novo email, 3 = Verificar código do novo email
}

// Buscar dados do usuário para preencher o formulário
try {
    // Buscar todos os campos necessários, incluindo os de alteração de e-mail
    $sql_fetch = "SELECT nome, email, avatar, new_email_pending, new_email_code, new_email_code_expires, email_change_code, email_change_expires FROM usuarios WHERE id = :id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(":id", $usuario_id);
    $stmt_fetch->execute();
    $usuario = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        session_destroy();
        header("Location: login.php?erro=usuario_nao_encontrado");
        exit;
    }
    
    // Garantir que o avatar padrão seja usado se o campo estiver vazio ou o arquivo não existir
    $default_avatar_path = "img/img-sem-foto.jpg";
    if (empty($usuario["avatar"]) || !file_exists($usuario["avatar"])) {
        $usuario["avatar"] = $default_avatar_path;
    }

} catch (PDOException $e) {
    $mensagem = "Erro ao buscar dados do perfil.";
    $tipo_mensagem = "error";
    error_log("Erro PDO ao buscar perfil: " . $e->getMessage());
}

// --- Lógica de Processamento dos Formulários --- //

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validar token CSRF para todas as ações POST
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensagem = "Erro de validação CSRF. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        // --- Ação: Atualizar Nome ---
        if (isset($_POST["update_nome"])) {
            $nome = filter_input(INPUT_POST, "nome", FILTER_SANITIZE_SPECIAL_CHARS);

            if (empty($nome)) {
                $mensagem = "Nome é obrigatório!";
                $tipo_mensagem = "error";
                $secao_ativa = 'perfil';
            } else {
                try {
                    $sql_update = "UPDATE usuarios SET nome = :nome WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->bindParam(":nome", $nome);
                    $stmt_update->bindParam(":id", $usuario_id);

                    if ($stmt_update->execute()) {
                        $mensagem = "Nome atualizado com sucesso!";
                        $tipo_mensagem = "success";
                        // Atualizar dados na variável $usuario e na sessão
                        $usuario["nome"] = $nome;
                        $_SESSION["usuario_nome"] = $nome;
                    } else {
                        $mensagem = "Erro ao atualizar nome no banco de dados!";
                        $tipo_mensagem = "error";
                    }
                    $secao_ativa = 'perfil';
                } catch (PDOException $e) {
                    $mensagem = "Erro no banco de dados ao atualizar nome.";
                    $tipo_mensagem = "error";
                    error_log("Exceção PDO ao atualizar nome: " . $e->getMessage());
                    $secao_ativa = 'perfil';
                }
            }
        }
        
        // --- Ação: Solicitar Alteração de E-mail ---
        elseif (isset($_POST["request_email_change"])) {
            try {
                // Gerar código e data de expiração
                $codigo = substr(str_shuffle("0123456789"), 0, 6);
                $expira = new DateTime("+15 minutes");
                $expira_formatado = $expira->format("Y-m-d H:i:s");

                // Salvar código no banco
                $sql_update = "UPDATE usuarios SET email_change_code = :code, email_change_expires = :expires WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(":code", $codigo);
                $stmt_update->bindParam(":expires", $expira_formatado);
                $stmt_update->bindParam(":id", $usuario_id);

                if ($stmt_update->execute()) {
                    // Enviar e-mail para o endereço atual
                    $mail = new PHPMailer(true);
                    try {
                        // Configurações do Servidor com as credenciais fornecidas
                        $mail->isSMTP();
                        $mail->Host       = "mail.simonatosys.com.br";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "mahru@simonatosys.com.br";
                        $mail->Password   = "eNbO.Gp)8}lM";
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL/TLS
                        $mail->Port       = 465;

                        // Remetente e Destinatário
                        $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                        $mail->addAddress($usuario["email"]); // E-mail ATUAL do usuário

                        // Conteúdo
                        $mail->isHTML(true);
                        $mail->Subject = "Código de verificação para alteração de e-mail - MAHRU";
                        $mail->Body    = "Olá " . htmlspecialchars($usuario["nome"]) . ",<br><br>Você solicitou a alteração do seu e-mail de acesso ao MAHRU.<br>Para prosseguir com esta alteração, utilize o código abaixo na página do seu perfil:<br><br><b>" . $codigo . "</b><br><br>Este código expira em 15 minutos.<br>Se você não solicitou esta alteração, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe MAHRU";
                        $mail->AltBody = "Olá " . $usuario["nome"] . ",\n\nVocê solicitou a alteração do seu e-mail de acesso ao MAHRU.\nPara prosseguir com esta alteração, utilize o código abaixo na página do seu perfil:\n\n" . $codigo . "\n\nEste código expira em 15 minutos.\nSe você não solicitou esta alteração, ignore este e-mail.\n\nAtenciosamente,\nEquipe MAHRU";

                        $mail->send();
                        $mensagem = "Código de verificação enviado para seu e-mail atual (" . htmlspecialchars($usuario["email"]) . "). Verifique sua caixa de entrada.";
                        $tipo_mensagem = "success";
                        
                        // Atualizar dados do usuário na página
                        $usuario["email_change_code"] = $codigo;
                        $usuario["email_change_expires"] = $expira_formatado;
                        
                        // Avançar para a próxima sub-etapa
                        $sub_etapa_email = 2;
                    } catch (Exception $e) {
                        $mensagem = "Erro ao enviar e-mail de verificação: " . $mail->ErrorInfo;
                        $tipo_mensagem = "error";
                        error_log("Mailer Error: {$mail->ErrorInfo}");
                    }
                } else {
                    $mensagem = "Erro ao salvar código de verificação no banco de dados.";
                    $tipo_mensagem = "error";
                }
                $secao_ativa = 'email';
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados ao gerar código de verificação.";
                $tipo_mensagem = "error";
                error_log("Exceção PDO ao gerar código: " . $e->getMessage());
                $secao_ativa = 'email';
            }
        }
        
        // --- Ação: Verificar Código do Email Atual ---
        elseif (isset($_POST["verify_email_code"])) {
            $codigo_verificacao = filter_input(INPUT_POST, "codigo_verificacao", FILTER_SANITIZE_SPECIAL_CHARS);
            $novo_email = filter_input(INPUT_POST, "novo_email", FILTER_VALIDATE_EMAIL);
            
            if (empty($codigo_verificacao)) {
                $mensagem = "Código de verificação é obrigatório.";
                $tipo_mensagem = "error";
            } elseif (empty($usuario["email_change_code"]) || empty($usuario["email_change_expires"])) {
                $mensagem = "Nenhum código de verificação foi solicitado ou os dados são inválidos.";
                $tipo_mensagem = "error";
            } elseif (empty($novo_email)) {
                $mensagem = "Novo e-mail é obrigatório.";
                $tipo_mensagem = "error";
            } elseif ($novo_email === $usuario["email"]) {
                $mensagem = "O novo e-mail deve ser diferente do atual.";
                $tipo_mensagem = "error";
            } else {
                // Verificar código e expiração
                $agora = new DateTime();
                $expira = new DateTime($usuario["email_change_expires"]);
                
                if ($codigo_verificacao !== $usuario["email_change_code"]) {
                    $mensagem = "Código de verificação inválido.";
                    $tipo_mensagem = "error";
                } elseif ($agora > $expira) {
                    $mensagem = "Código de verificação expirado. Solicite novamente.";
                    $tipo_mensagem = "error";
                    // Limpar dados expirados
                    $sql_clear = "UPDATE usuarios SET email_change_code = NULL, email_change_expires = NULL WHERE id = :id";
                    $stmt_clear = $pdo->prepare($sql_clear);
                    $stmt_clear->bindParam(":id", $usuario_id);
                    $stmt_clear->execute();
                    
                    $sub_etapa_email = 1;
                } else {
                    try {
                        // Verificar se o novo email já existe
                        $sql_check_email = "SELECT id FROM usuarios WHERE email = :email AND id != :id";
                        $stmt_check_email = $pdo->prepare($sql_check_email);
                        $stmt_check_email->bindParam(":email", $novo_email);
                        $stmt_check_email->bindParam(":id", $usuario_id);
                        $stmt_check_email->execute();
                        
                        if ($stmt_check_email->fetch()) {
                            $mensagem = "Este novo e-mail já está em uso por outro usuário.";
                            $tipo_mensagem = "error";
                        } else {
                            // Gerar código para o novo email
                            $codigo_novo_email = substr(str_shuffle("0123456789"), 0, 6);
                            $expira_novo = new DateTime("+15 minutes");
                            $expira_novo_formatado = $expira_novo->format("Y-m-d H:i:s");
                            
                            // Salvar dados pendentes no banco
                            $sql_update = "UPDATE usuarios SET new_email_pending = :new_email, new_email_code = :code, new_email_code_expires = :expires WHERE id = :id";
                            $stmt_update = $pdo->prepare($sql_update);
                            $stmt_update->bindParam(":new_email", $novo_email);
                            $stmt_update->bindParam(":code", $codigo_novo_email);
                            $stmt_update->bindParam(":expires", $expira_novo_formatado);
                            $stmt_update->bindParam(":id", $usuario_id);
                            
                            if ($stmt_update->execute()) {
                                // Enviar e-mail para o NOVO endereço
                                $mail = new PHPMailer(true);
                                try {
                                    // Configurações do Servidor com as credenciais fornecidas
                                    $mail->isSMTP();
                                    $mail->Host       = "mail.simonatosys.com.br";
                                    $mail->SMTPAuth   = true;
                                    $mail->Username   = "mahru@simonatosys.com.br";
                                    $mail->Password   = "eNbO.Gp)8}lM";
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL/TLS
                                    $mail->Port       = 465;
                                    
                                    // Remetente e Destinatário
                                    $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                                    $mail->addAddress($novo_email); // NOVO e-mail
                                    
                                    // Conteúdo
                                    $mail->isHTML(true);
                                    $mail->Subject = "Confirme seu novo e-mail - MAHRU";
                                    $mail->Body    = "Olá " . htmlspecialchars($usuario["nome"]) . ",<br><br>Você solicitou a alteração do seu e-mail de acesso ao MAHRU para este endereço.<br>Para confirmar este novo e-mail, utilize o código abaixo na página do seu perfil:<br><br><b>" . $codigo_novo_email . "</b><br><br>Este código expira em 15 minutos.<br>Se você não solicitou esta alteração, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe MAHRU";
                                    $mail->AltBody = "Olá " . $usuario["nome"] . ",\n\nVocê solicitou a alteração do seu e-mail de acesso ao MAHRU para este endereço.\nPara confirmar este novo e-mail, utilize o código abaixo na página do seu perfil:\n\n" . $codigo_novo_email . "\n\nEste código expira em 15 minutos.\nSe você não solicitou esta alteração, ignore este e-mail.\n\nAtenciosamente,\nEquipe MAHRU";
                                    
                                    $mail->send();
                                    $mensagem = "Código de confirmação enviado para o novo e-mail (" . htmlspecialchars($novo_email) . "). Verifique a caixa de entrada do novo e-mail.";
                                    $tipo_mensagem = "success";
                                    
                                    // Atualizar dados do usuário na página
                                    $usuario["new_email_pending"] = $novo_email;
                                    $usuario["new_email_code"] = $codigo_novo_email;
                                    $usuario["new_email_code_expires"] = $expira_novo_formatado;
                                    
                                    // Avançar para a próxima sub-etapa
                                    $sub_etapa_email = 3;
                                } catch (Exception $e) {
                                    $mensagem = "Erro ao enviar e-mail de confirmação: " . $mail->ErrorInfo;
                                    $tipo_mensagem = "error";
                                    error_log("Mailer Error: {$mail->ErrorInfo}");
                                }
                            } else {
                                $mensagem = "Erro ao salvar solicitação no banco de dados.";
                                $tipo_mensagem = "error";
                            }
                        }
                    } catch (PDOException $e) {
                        $mensagem = "Erro no banco de dados ao verificar e-mail.";
                        $tipo_mensagem = "error";
                        error_log("Exceção PDO ao verificar e-mail: " . $e->getMessage());
                    }
                }
            }
            $secao_ativa = 'email';
        }
        
        // --- Ação: Verificar Código do Novo Email ---
        elseif (isset($_POST["verify_new_email_code"])) {
            $codigo_novo_email = filter_input(INPUT_POST, "codigo_novo_email", FILTER_SANITIZE_SPECIAL_CHARS);
            
            if (empty($codigo_novo_email)) {
                $mensagem = "Código de confirmação é obrigatório.";
                $tipo_mensagem = "error";
            } elseif (empty($usuario["new_email_pending"]) || empty($usuario["new_email_code"]) || empty($usuario["new_email_code_expires"])) {
                $mensagem = "Nenhuma solicitação de alteração de e-mail pendente ou dados inválidos.";
                $tipo_mensagem = "error";
            } else {
                // Verificar código e expiração
                $agora = new DateTime();
                $expira = new DateTime($usuario["new_email_code_expires"]);
                
                if ($codigo_novo_email !== $usuario["new_email_code"]) {
                    $mensagem = "Código de confirmação inválido.";
                    $tipo_mensagem = "error";
                } elseif ($agora > $expira) {
                    $mensagem = "Código de confirmação expirado. Solicite novamente.";
                    $tipo_mensagem = "error";
                    // Limpar dados expirados
                    $sql_clear = "UPDATE usuarios SET new_email_pending = NULL, new_email_code = NULL, new_email_code_expires = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id";
                    $stmt_clear = $pdo->prepare($sql_clear);
                    $stmt_clear->bindParam(":id", $usuario_id);
                    $stmt_clear->execute();
                    
                    $sub_etapa_email = 1;
                } else {
                    try {
                        // Obter o novo email pendente para usar na atualização
                        $novo_email = $usuario["new_email_pending"];
                        
                        // Atualizar e-mail do usuário - usando diretamente a variável em vez de referência
                        $sql_update = "UPDATE usuarios SET email = :novo_email, new_email_pending = NULL, new_email_code = NULL, new_email_code_expires = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id";
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->bindParam(":novo_email", $novo_email);
                        $stmt_update->bindParam(":id", $usuario_id);
                        
                        // Log para depuração
                        error_log("Tentando atualizar email para: " . $novo_email . " para o usuário ID: " . $usuario_id);
                        
                        if ($stmt_update->execute()) {
                            // Log de sucesso
                            error_log("Email atualizado com sucesso para: " . $novo_email);
                            
                            $mensagem = "E-mail alterado com sucesso para " . htmlspecialchars($novo_email) . "!";
                            $tipo_mensagem = "success";
                            
                            // Atualizar dados na variável $usuario
                            $email_antigo = $usuario["email"];
                            $usuario["email"] = $novo_email;
                            $usuario["new_email_pending"] = null;
                            $usuario["new_email_code"] = null;
                            $usuario["new_email_code_expires"] = null;
                            $usuario["email_change_code"] = null;
                            $usuario["email_change_expires"] = null;
                            
                            // Atualizar email na sessão
                            $_SESSION["usuario_email"] = $novo_email;
                            
                            // Voltar para a etapa inicial
                            $sub_etapa_email = 1;
                            
                            // Enviar e-mail de confirmação para o e-mail antigo
                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host       = "mail.simonatosys.com.br";
                                $mail->SMTPAuth   = true;
                                $mail->Username   = "mahru@simonatosys.com.br";
                                $mail->Password   = "eNbO.Gp)8}lM";
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                $mail->Port       = 465;
                                
                                $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                                $mail->addAddress($email_antigo);
                                
                                $mail->isHTML(true);
                                $mail->Subject = "Seu e-mail foi alterado - MAHRU";
                                $mail->Body    = "Olá " . htmlspecialchars($usuario["nome"]) . ",<br><br>Seu e-mail de acesso ao MAHRU foi alterado com sucesso de <b>" . htmlspecialchars($email_antigo) . "</b> para <b>" . htmlspecialchars($novo_email) . "</b>.<br><br>Se você não realizou esta alteração, entre em contato com o administrador do sistema imediatamente.<br><br>Atenciosamente,<br>Equipe MAHRU";
                                $mail->AltBody = "Olá " . $usuario["nome"] . ",\n\nSeu e-mail de acesso ao MAHRU foi alterado com sucesso de " . $email_antigo . " para " . $novo_email . ".\n\nSe você não realizou esta alteração, entre em contato com o administrador do sistema imediatamente.\n\nAtenciosamente,\nEquipe MAHRU";
                                
                                $mail->send();
                            } catch (Exception $e) {
                                error_log("Erro ao enviar e-mail de confirmação para e-mail antigo: " . $mail->ErrorInfo);
                            }
                            
                            // Redirecionar para atualizar a página e mostrar o novo email
                            header("Location: editar-perfil.php?secao=email&msg=Email alterado com sucesso para " . urlencode($novo_email) . "&tipo=success");
                            exit;
                        } else {
                            $mensagem = "Erro ao atualizar e-mail no banco de dados!";
                            $tipo_mensagem = "error";
                            error_log("Erro ao executar update de email: " . print_r($stmt_update->errorInfo(), true));
                        }
                    } catch (PDOException $e) {
                        $mensagem = "Erro no banco de dados ao atualizar e-mail.";
                        $tipo_mensagem = "error";
                        error_log("Exceção PDO ao atualizar e-mail: " . $e->getMessage());
                    }
                }
            }
            $secao_ativa = 'email';
        }
        
        // --- Ação: Cancelar Alteração de Email ---
        elseif (isset($_POST["cancel_email_change"])) {
            try {
                // Limpar todos os dados de alteração de e-mail
                $sql_clear = "UPDATE usuarios SET new_email_pending = NULL, new_email_code = NULL, new_email_code_expires = NULL, email_change_code = NULL, email_change_expires = NULL WHERE id = :id";
                $stmt_clear = $pdo->prepare($sql_clear);
                $stmt_clear->bindParam(":id", $usuario_id);
                
                if ($stmt_clear->execute()) {
                    $mensagem = "Processo de alteração de e-mail cancelado.";
                    $tipo_mensagem = "info";
                    
                    // Limpar dados na variável $usuario
                    $usuario["new_email_pending"] = null;
                    $usuario["new_email_code"] = null;
                    $usuario["new_email_code_expires"] = null;
                    $usuario["email_change_code"] = null;
                    $usuario["email_change_expires"] = null;
                    
                    // Voltar para a etapa inicial
                    $sub_etapa_email = 1;
                } else {
                    $mensagem = "Erro ao cancelar processo de alteração de e-mail.";
                    $tipo_mensagem = "error";
                }
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados ao cancelar alteração de e-mail.";
                $tipo_mensagem = "error";
                error_log("Exceção PDO ao cancelar alteração de e-mail: " . $e->getMessage());
            }
            $secao_ativa = 'email';
        }
        
        // --- Ação: Atualizar Avatar ---
        elseif (isset($_POST["update_avatar"])) {
            // Verificar se foi enviado um arquivo
            if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] === UPLOAD_ERR_OK) {
                $avatar_tmp = $_FILES["avatar"]["tmp_name"];
                $avatar_size = $_FILES["avatar"]["size"];
                $avatar_nome_original = basename($_FILES["avatar"]["name"]);
                $avatar_ext = strtolower(pathinfo($avatar_nome_original, PATHINFO_EXTENSION));
                
                // Validar extensão e tamanho
                $extensoes_permitidas = ["jpg", "jpeg", "png", "gif"];
                $tamanho_maximo_bytes = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($avatar_ext, $extensoes_permitidas)) {
                    $mensagem = "Tipo de arquivo inválido. Permitidos: JPG, JPEG, PNG, GIF.";
                    $tipo_mensagem = "error";
                } elseif ($avatar_size > $tamanho_maximo_bytes) {
                    $mensagem = "Arquivo muito grande. Tamanho máximo: 2MB.";
                    $tipo_mensagem = "error";
                } else {
                    // Criar diretório de uploads se não existir
                    $diretorio_avatar = "uploads/avatars/";
                    if (!is_dir($diretorio_avatar)) {
                        if (!mkdir($diretorio_avatar, 0755, true)) {
                            $mensagem = "Erro ao criar diretório de uploads.";
                            $tipo_mensagem = "error";
                        }
                    }
                    
                    if ($tipo_mensagem !== "error") {
                        // Gerar nome único para o arquivo
                        $novo_nome = "avatar_" . $usuario_id . "_" . uniqid() . "." . $avatar_ext;
                        $caminho_destino = $diretorio_avatar . $novo_nome;
                        
                        // Mover arquivo para o destino
                        if (move_uploaded_file($avatar_tmp, $caminho_destino)) {
                            try {
                                // Remover avatar antigo se não for o padrão
                                if ($usuario["avatar"] !== $default_avatar_path && file_exists($usuario["avatar"])) {
                                    @unlink($usuario["avatar"]);
                                }
                                
                                // Atualizar caminho do avatar no banco de dados
                                $sql_update = "UPDATE usuarios SET avatar = :avatar WHERE id = :id";
                                $stmt_update = $pdo->prepare($sql_update);
                                $stmt_update->bindParam(":avatar", $caminho_destino);
                                $stmt_update->bindParam(":id", $usuario_id);
                                
                                if ($stmt_update->execute()) {
                                    $mensagem = "Avatar atualizado com sucesso!";
                                    $tipo_mensagem = "success";
                                    
                                    // Atualizar dados na variável $usuario e na sessão
                                    $usuario["avatar"] = $caminho_destino;
                                    $_SESSION["usuario_avatar"] = $caminho_destino;
                                } else {
                                    $mensagem = "Erro ao atualizar avatar no banco de dados!";
                                    $tipo_mensagem = "error";
                                    
                                    // Remover arquivo se o banco falhou
                                    @unlink($caminho_destino);
                                }
                            } catch (PDOException $e) {
                                $mensagem = "Erro no banco de dados ao atualizar avatar.";
                                $tipo_mensagem = "error";
                                error_log("Exceção PDO ao atualizar avatar: " . $e->getMessage());
                                
                                // Remover arquivo se o banco falhou
                                @unlink($caminho_destino);
                            }
                        } else {
                            $mensagem = "Erro ao mover arquivo para o destino.";
                            $tipo_mensagem = "error";
                        }
                    }
                }
            } elseif (isset($_POST["remover_avatar"]) && $_POST["remover_avatar"] == 1) {
                // Remover avatar atual e definir como padrão
                try {
                    // Remover arquivo se não for o padrão
                    if ($usuario["avatar"] !== $default_avatar_path && file_exists($usuario["avatar"])) {
                        @unlink($usuario["avatar"]);
                    }
                    
                    // Atualizar para o avatar padrão no banco de dados
                    $sql_update = "UPDATE usuarios SET avatar = :avatar WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->bindParam(":avatar", $default_avatar_path);
                    $stmt_update->bindParam(":id", $usuario_id);
                    
                    if ($stmt_update->execute()) {
                        $mensagem = "Avatar removido com sucesso!";
                        $tipo_mensagem = "success";
                        
                        // Atualizar dados na variável $usuario e na sessão
                        $usuario["avatar"] = $default_avatar_path;
                        $_SESSION["usuario_avatar"] = $default_avatar_path;
                    } else {
                        $mensagem = "Erro ao remover avatar no banco de dados!";
                        $tipo_mensagem = "error";
                    }
                } catch (PDOException $e) {
                    $mensagem = "Erro no banco de dados ao remover avatar.";
                    $tipo_mensagem = "error";
                    error_log("Exceção PDO ao remover avatar: " . $e->getMessage());
                }
            } elseif (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] !== UPLOAD_ERR_NO_FILE) {
                // Tratar outros erros de upload
                switch ($_FILES["avatar"]["error"]) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $mensagem = "Arquivo excede o tamanho máximo permitido.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $mensagem = "O upload foi feito parcialmente.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $mensagem = "Falta uma pasta temporária.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $mensagem = "Falha ao escrever arquivo em disco.";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $mensagem = "Uma extensão PHP interrompeu o upload.";
                        break;
                    default:
                        $mensagem = "Erro desconhecido no upload.";
                        break;
                }
                $tipo_mensagem = "error";
            } else {
                $mensagem = "Nenhum arquivo selecionado.";
                $tipo_mensagem = "warning";
            }
            $secao_ativa = 'avatar';
        }
        
        // --- Ação: Atualizar Senha ---
        elseif (isset($_POST["update_senha"])) {
            $senha_atual = $_POST["senha_atual"] ?? "";
            $nova_senha = $_POST["nova_senha"] ?? "";
            $confirmar_senha = $_POST["confirmar_senha"] ?? "";
            
            if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                $mensagem = "Todos os campos são obrigatórios!";
                $tipo_mensagem = "error";
            } else {
                try {
                    // Verificar senha atual
                    $sql_check = "SELECT senha FROM usuarios WHERE id = :id";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->bindParam(":id", $usuario_id);
                    $stmt_check->execute();
                    $hash_senha_atual = $stmt_check->fetchColumn();
                    
                    if (!password_verify($senha_atual, $hash_senha_atual)) {
                        $mensagem = "Senha atual incorreta!";
                        $tipo_mensagem = "error";
                    } else {
                        // Validar nova senha
                        if (strlen($nova_senha) < 6) {
                            $mensagem = "A nova senha deve ter pelo menos 6 caracteres!";
                            $tipo_mensagem = "error";
                        } elseif ($nova_senha !== $confirmar_senha) {
                            $mensagem = "A nova senha e a confirmação não coincidem!";
                            $tipo_mensagem = "error";
                        } else {
                            $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                            
                            $sql_update = "UPDATE usuarios SET senha = :senha WHERE id = :id";
                            $stmt_update = $pdo->prepare($sql_update);
                            $stmt_update->bindParam(":senha", $hash_nova_senha);
                            $stmt_update->bindParam(":id", $usuario_id);
                            
                            if ($stmt_update->execute()) {
                                $mensagem = "Senha atualizada com sucesso!";
                                $tipo_mensagem = "success";
                            } else {
                                $mensagem = "Erro ao atualizar senha no banco de dados!";
                                $tipo_mensagem = "error";
                            }
                        }
                    }
                } catch (PDOException $e) {
                    $mensagem = "Erro no banco de dados ao verificar senha.";
                    $tipo_mensagem = "error";
                    error_log("Exceção PDO ao verificar senha: " . $e->getMessage());
                }
            }
            $secao_ativa = 'senha';
        }
    }
    
    // Regenerar token CSRF após qualquer ação POST
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
} else {
    // Gerar token CSRF inicial para GET requests
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    
    // Verificar mensagem via GET
    if (isset($_GET['msg'])) {
        $mensagem = $_GET['msg'];
        $tipo_mensagem = isset($_GET['tipo']) ? $_GET['tipo'] : "success";
    }
    
    // Limpar código expirado ao carregar a página (GET)
    if (!empty($usuario["email_change_expires"])) {
        $agora = new DateTime();
        $expira = new DateTime($usuario["email_change_expires"]);
        if ($agora > $expira) {
            try {
                $sql_clear_get = "UPDATE usuarios SET email_change_code = NULL, email_change_expires = NULL WHERE id = :id";
                $stmt_clear_get = $pdo->prepare($sql_clear_get);
                $stmt_clear_get->bindParam(":id", $usuario_id);
                $stmt_clear_get->execute();
                
                $usuario["email_change_code"] = null;
                $usuario["email_change_expires"] = null;
            } catch (PDOException $e) {
                error_log("Erro PDO ao limpar código expirado no GET: " . $e->getMessage());
            }
        }
    }
}

// Define o título da página ANTES de incluir o header
$page_title = "Editar Perfil - MAHRU";
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Editar Perfil</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Editar Perfil</div>
            </div>
        </div>

        <div class="section-body">
            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo ($tipo_mensagem === "success" ? "success-message" : ($tipo_mensagem === "error" ? "error-message" : ($tipo_mensagem === "warning" ? "alert-warning" : "alert-info"))); ?> animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>
            
            <!-- Cabeçalho do Perfil -->
            <div class="dashboard-card animate__animated animate__fadeInUp">
                <div class="profile-header-content">
                    <div class="avatar-container">
                        <div class="avatar-preview">
                            <img src="<?php echo htmlspecialchars($usuario["avatar"]); ?>" alt="Avatar" class="profile-avatar">
                        </div>
                    </div>
                    <div class="user-info">
                        <h2 class="profile-name"><?php echo htmlspecialchars($usuario["nome"]); ?></h2>
                        <div class="profile-email"><?php echo htmlspecialchars($usuario["email"]); ?></div>
                        <div class="profile-actions mt-3">
                            <a href="painel.php" class="btn-cancel">
                                <i class="fas fa-arrow-left"></i> Voltar ao Painel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seções de Edição -->
            <div class="row">
                <div class="col-12">
                    <!-- Seção: Informações Pessoais -->
                    <div class="dashboard-card animate__animated animate__fadeInUp" id="section-perfil">
                        <div class="profile-section-header" data-bs-toggle="collapse" data-bs-target="#collapse-perfil" aria-expanded="<?php echo $secao_ativa === 'perfil' ? 'true' : 'false'; ?>" aria-controls="collapse-perfil">
                            <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div id="collapse-perfil" class="collapse <?php echo $secao_ativa === 'perfil' ? 'show' : ''; ?>" aria-labelledby="section-perfil">
                            <div class="profile-section-body">
                                <form action="editar-perfil.php?secao=perfil" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="form-group">
                                        <label for="nome" class="form-label">Nome Completo</label>
                                        <input type="text" name="nome" id="nome" class="form-control" value="<?php echo htmlspecialchars($usuario["nome"]); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($usuario["email"]); ?>" readonly>
                                        <small class="form-text text-muted">Para alterar seu email, use a seção "Alterar Email" abaixo.</small>
                                    </div>
                                    
                                    <div class="text-right">
                                        <button type="submit" name="update_nome" class="btn-submit">
                                            <i class="fas fa-save"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Alterar Email -->
                    <div class="dashboard-card animate__animated animate__fadeInUp" id="section-email">
                        <div class="profile-section-header" data-bs-toggle="collapse" data-bs-target="#collapse-email" aria-expanded="<?php echo $secao_ativa === 'email' ? 'true' : 'false'; ?>" aria-controls="collapse-email">
                            <h3><i class="fas fa-envelope"></i> Alterar Email</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div id="collapse-email" class="collapse <?php echo $secao_ativa === 'email' ? 'show' : ''; ?>" aria-labelledby="section-email">
                            <div class="profile-section-body">
                                <!-- Etapas do processo de alteração de email -->
                                <div class="email-steps">
                                    <div class="email-step <?php echo $sub_etapa_email === 1 ? 'active' : ''; ?>">
                                        1. Solicitar Código
                                    </div>
                                    <div class="email-step <?php echo $sub_etapa_email === 2 ? 'active' : ''; ?>">
                                        2. Verificar e Inserir Novo Email
                                    </div>
                                    <div class="email-step <?php echo $sub_etapa_email === 3 ? 'active' : ''; ?>">
                                        3. Confirmar Novo Email
                                    </div>
                                </div>
                                
                                <?php if ($sub_etapa_email === 1): ?>
                                <!-- Etapa 1: Solicitar Código -->
                                <form action="editar-perfil.php?secao=email&sub_etapa=1" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="alert-info p-3 mb-4 rounded">
                                        <i class="fas fa-info-circle"></i> Para alterar seu endereço de e-mail, primeiro enviaremos um código de verificação para seu e-mail atual: <strong><?php echo htmlspecialchars($usuario["email"]); ?></strong>
                                    </div>
                                    
                                    <div class="text-center">
                                        <button type="submit" name="request_email_change" class="btn-submit">
                                            <i class="fas fa-paper-plane"></i> Enviar Código de Verificação
                                        </button>
                                    </div>
                                </form>
                                
                                <?php elseif ($sub_etapa_email === 2): ?>
                                <!-- Etapa 2: Verificar Código e Inserir Novo Email -->
                                <form action="editar-perfil.php?secao=email&sub_etapa=2" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="alert-warning p-3 mb-4 rounded">
                                        <i class="fas fa-exclamation-triangle"></i> Um código de verificação foi enviado para <strong><?php echo htmlspecialchars($usuario["email"]); ?></strong>. Verifique sua caixa de entrada e insira o código abaixo.
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="codigo_verificacao" class="form-label">Código de Verificação</label>
                                        <input type="text" name="codigo_verificacao" id="codigo_verificacao" class="form-control verification-code-input" required maxlength="6" pattern="[0-9]{6}" title="Digite o código de 6 dígitos recebido" autocomplete="off">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="novo_email" class="form-label">Novo Endereço de E-mail</label>
                                        <input type="email" name="novo_email" id="novo_email" class="form-control" required>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="cancel_email_change" class="btn-cancel">
                                            <i class="fas fa-times"></i> Cancelar
                                        </button>
                                        
                                        <button type="submit" name="verify_email_code" class="btn-submit">
                                            <i class="fas fa-check"></i> Verificar e Continuar
                                        </button>
                                    </div>
                                </form>
                                
                                <?php elseif ($sub_etapa_email === 3): ?>
                                <!-- Etapa 3: Verificar Código do Novo Email -->
                                <form action="editar-perfil.php?secao=email&sub_etapa=3" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="alert-warning p-3 mb-4 rounded">
                                        <i class="fas fa-exclamation-triangle"></i> Um código de confirmação foi enviado para o novo e-mail: <strong><?php echo htmlspecialchars($usuario["new_email_pending"]); ?></strong>. Verifique a caixa de entrada do novo e-mail e insira o código abaixo.
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="codigo_novo_email" class="form-label">Código de Confirmação</label>
                                        <input type="text" name="codigo_novo_email" id="codigo_novo_email" class="form-control verification-code-input" required maxlength="6" pattern="[0-9]{6}" title="Digite o código de 6 dígitos recebido" autocomplete="off">
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="cancel_email_change" class="btn-cancel">
                                            <i class="fas fa-times"></i> Cancelar
                                        </button>
                                        
                                        <button type="submit" name="verify_new_email_code" class="btn-submit">
                                            <i class="fas fa-check"></i> Confirmar Novo Email
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Alterar Avatar -->
                    <div class="dashboard-card animate__animated animate__fadeInUp" id="section-avatar">
                        <div class="profile-section-header" data-bs-toggle="collapse" data-bs-target="#collapse-avatar" aria-expanded="<?php echo $secao_ativa === 'avatar' ? 'true' : 'false'; ?>" aria-controls="collapse-avatar">
                            <h3><i class="fas fa-image"></i> Alterar Imagem de Perfil</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div id="collapse-avatar" class="collapse <?php echo $secao_ativa === 'avatar' ? 'show' : ''; ?>" aria-labelledby="section-avatar">
                            <div class="profile-section-body">
                                <form action="editar-perfil.php?secao=avatar" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="avatar-container">
                                        <div class="avatar-preview">
                                            <img src="<?php echo htmlspecialchars($usuario["avatar"]); ?>" alt="Avatar Atual" id="avatar-preview">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="avatar" class="form-label">Selecionar Nova Imagem</label>
                                        <input type="file" name="avatar" id="avatar" class="form-control" accept="image/png, image/jpeg, image/gif">
                                        <small class="form-text text-muted">Tamanho máximo: 2MB. Formatos: JPG, PNG, GIF.</small>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <div class="form-check">
                                            <input type="checkbox" name="remover_avatar" id="remover_avatar" value="1" class="form-check-input">
                                            <label for="remover_avatar" class="form-check-label">Remover imagem atual</label>
                                        </div>
                                        
                                        <button type="submit" name="update_avatar" class="btn-submit">
                                            <i class="fas fa-save"></i> Atualizar Imagem
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Alterar Senha -->
                    <div class="dashboard-card animate__animated animate__fadeInUp" id="section-senha">
                        <div class="profile-section-header" data-bs-toggle="collapse" data-bs-target="#collapse-senha" aria-expanded="<?php echo $secao_ativa === 'senha' ? 'true' : 'false'; ?>" aria-controls="collapse-senha">
                            <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div id="collapse-senha" class="collapse <?php echo $secao_ativa === 'senha' ? 'show' : ''; ?>" aria-labelledby="section-senha">
                            <div class="profile-section-body">
                                <form action="editar-perfil.php?secao=senha" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">
                                    
                                    <div class="form-group">
                                        <label for="senha_atual" class="form-label">Senha Atual</label>
                                        <div class="password-container">
                                            <input type="password" name="senha_atual" id="senha_atual" class="form-control" required>
                                            <i class="fas fa-eye toggle-password" data-target="senha_atual"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nova_senha" class="form-label">Nova Senha</label>
                                        <div class="password-container">
                                            <input type="password" name="nova_senha" id="nova_senha" class="form-control" required minlength="6">
                                            <i class="fas fa-eye toggle-password" data-target="nova_senha"></i>
                                        </div>
                                        <small class="form-text text-muted">Mínimo de 6 caracteres.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                        <div class="password-container">
                                            <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" required minlength="6">
                                            <i class="fas fa-eye toggle-password" data-target="confirmar_senha"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <button type="submit" name="update_senha" class="btn-submit">
                                            <i class="fas fa-key"></i> Alterar Senha
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
/* Estilos específicos para esta página */
.profile-section-header {
    padding: 20px 25px;
    background-color: #1a1a1a;
    border-bottom: 1px solid #333;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.profile-section-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--primary-color);
    display: flex;
    align-items: center;
}

.profile-section-header h3 i {
    margin-right: 10px;
    color: var(--primary-color);
    font-size: 20px;
}

.profile-section-header .toggle-icon {
    transition: transform 0.3s ease;
    color: var(--primary-color);
}

.profile-section-header[aria-expanded="true"] .toggle-icon {
    transform: rotate(180deg);
}

.profile-section-body {
    padding: 25px;
    background-color: #2a2a2a;
}

.profile-header-content {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-name {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--primary-color);
}

.profile-email {
    font-size: 16px;
    color: var(--muted-text);
    margin-bottom: 15px;
}

/* Código de verificação */
.verification-code-input {
    letter-spacing: 5px;
    font-size: 20px;
    text-align: center;
    font-weight: 600;
    background-color: #333;
    color: var(--text-color);
}

/* Estilo para campos de senha */
.password-container {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--muted-text);
}

/* Estilo para etapas de email */
.email-steps {
    display: flex;
    margin-bottom: 20px;
    border-radius: 10px;
    overflow: hidden;
}

.email-step {
    flex: 1;
    padding: 10px;
    text-align: center;
    background-color: #333;
    position: relative;
    color: var(--muted-text);
}

.email-step.active {
    background-color: var(--primary-color);
    color: white;
    font-weight: 600;
}

.email-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
    border-left: 10px solid #333;
    z-index: 1;
}

.email-step.active:not(:last-child)::after {
    border-left-color: var(--primary-color);
}

.alert-info, .alert-warning {
    background-color: rgba(23, 162, 184, 0.2);
    color: var(--info-color);
    border: 1px solid var(--info-color);
}

.alert-warning {
    background-color: rgba(255, 193, 7, 0.2);
    color: var(--warning-color);
    border: 1px solid var(--warning-color);
}

/* Responsividade */
@media (max-width: 768px) {
    .profile-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .avatar-container {
        margin-right: 0;
        margin-bottom: 20px;
    }
}
</style>

<!-- Scripts específicos para esta página -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle de senha
    const togglePasswordIcons = document.querySelectorAll(".toggle-password");
    togglePasswordIcons.forEach(icon => {
        icon.addEventListener("click", function() {
            const targetId = this.getAttribute("data-target");
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                this.classList.remove("fa-eye");
                this.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                this.classList.remove("fa-eye-slash");
                this.classList.add("fa-eye");
            }
        });
    });
    
    // Preview de avatar
    const avatarInput = document.getElementById("avatar");
    const avatarPreview = document.getElementById("avatar-preview");
    const removerAvatarCheckbox = document.getElementById("remover_avatar");
    
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener("change", function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                    
                    // Desmarcar checkbox de remover avatar
                    if (removerAvatarCheckbox) {
                        removerAvatarCheckbox.checked = false;
                    }
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    if (removerAvatarCheckbox && avatarPreview) {
        const defaultAvatarPath = "<?php echo $default_avatar_path; ?>";
        
        removerAvatarCheckbox.addEventListener("change", function() {
            if (this.checked) {
                // Salvar a URL atual para restaurar se o usuário desmarcar
                avatarPreview.dataset.originalSrc = avatarPreview.src;
                avatarPreview.src = defaultAvatarPath;
                
                // Limpar input de arquivo
                if (avatarInput) {
                    avatarInput.value = "";
                }
            } else {
                // Restaurar a URL original se existir
                if (avatarPreview.dataset.originalSrc) {
                    avatarPreview.src = avatarPreview.dataset.originalSrc;
                }
            }
        });
    }
    
    // Formatação automática do código de verificação
    const codeInputs = document.querySelectorAll(".verification-code-input");
    codeInputs.forEach(input => {
        input.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "").substring(0, 6);
        });
    });
});
</script>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>
