<?php
/**
 * Formulário para alteração da senha administrativa
 * 
 * Este arquivo permite a alteração da senha administrativa do sistema
 * através de um código de verificação enviado por email.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_nivel"] !== "admin") {
    $_SESSION["error_message"] = "Acesso negado. Apenas administradores podem alterar a senha do sistema.";
    header("Location: painel.php");
    exit;
}

// Incluir arquivos necessários
require_once("db/conexao.php");
require_once("includes/verificacao_senha.php");

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require "vendor/phpmailer/phpmailer/src/Exception.php";
require "vendor/phpmailer/phpmailer/src/PHPMailer.php";
require "vendor/phpmailer/phpmailer/src/SMTP.php";

// Mensagens de erro/sucesso
$mensagem = "";
$tipo_mensagem = ""; // success, error

// Etapa do processo (1: solicitar código, 2: verificar código e alterar senha)
$etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 1;

// Processar solicitação de código
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["solicitar_codigo"])) {
    // Validar token CSRF
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensagem = "Erro de validação CSRF. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        // Obter email do administrador
        $email_admin = $_SESSION["usuario_email"] ?? $_POST["email_admin"];
        
        if (empty($email_admin) || !filter_var($email_admin, FILTER_VALIDATE_EMAIL)) {
            $mensagem = "Email inválido.";
            $tipo_mensagem = "error";
        } else {
            // Gerar código de verificação
            $codigo = substr(str_shuffle("0123456789"), 0, 6);
            $expira = new DateTime("+15 minutes");
            $expira_formatado = $expira->format("Y-m-d H:i:s");
            
            // Salvar código na sessão (em produção, seria melhor salvar no banco de dados)
            $_SESSION["codigo_alteracao_senha_admin"] = $codigo;
            $_SESSION["codigo_alteracao_senha_admin_expira"] = $expira_formatado;
            
            // Enviar email com o código
            $mail = new PHPMailer(true);
            try {
                // Configurações do Servidor
                $mail->isSMTP();
                $mail->Host       = "mail.simonatosys.com.br";
                $mail->SMTPAuth   = true;
                $mail->Username   = "mahru@simonatosys.com.br";
                $mail->Password   = "eNbO.Gp)8}lM";
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                
                // Remetente e Destinatário
                $mail->setFrom("mahru@simonatosys.com.br", "MAHRU Sistema");
                $mail->addAddress($email_admin);
                
                // Conteúdo
                $mail->isHTML(true);
                $mail->Subject = "Código para alteração da senha administrativa - MAHRU";
                $mail->Body    = "Olá Administrador,<br><br>Você solicitou a alteração da senha administrativa do sistema MAHRU.<br>Para prosseguir, utilize o código abaixo:<br><br><b>" . $codigo . "</b><br><br>Este código expira em 15 minutos.<br>Se você não solicitou esta alteração, ignore este e-mail e verifique a segurança da sua conta.<br><br>Atenciosamente,<br>Equipe MAHRU";
                $mail->AltBody = "Olá Administrador,\n\nVocê solicitou a alteração da senha administrativa do sistema MAHRU.\nPara prosseguir, utilize o código abaixo:\n\n" . $codigo . "\n\nEste código expira em 15 minutos.\nSe você não solicitou esta alteração, ignore este e-mail e verifique a segurança da sua conta.\n\nAtenciosamente,\nEquipe MAHRU";
                
                $mail->send();
                $mensagem = "Código de verificação enviado para o email " . htmlspecialchars($email_admin) . ". Verifique sua caixa de entrada.";
                $tipo_mensagem = "success";
                
                // Avançar para a próxima etapa
                $etapa = 2;
            } catch (Exception $e) {
                $mensagem = "Erro ao enviar e-mail: " . $mail->ErrorInfo;
                $tipo_mensagem = "error";
                error_log("Mailer Error: {$mail->ErrorInfo}");
                
                // Para fins de desenvolvimento/teste, mostrar o código gerado
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $mensagem .= " [DEBUG: Código gerado: " . $codigo . "]";
                }
            }
        }
    }
}

// Processar alteração de senha
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["alterar_senha"])) {
    // Validar token CSRF
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensagem = "Erro de validação CSRF. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        $codigo_verificacao = filter_input(INPUT_POST, "codigo_verificacao", FILTER_SANITIZE_SPECIAL_CHARS);
        $nova_senha = $_POST["nova_senha"] ?? "";
        $confirmar_senha = $_POST["confirmar_senha"] ?? "";
        
        // Validar dados
        if (empty($codigo_verificacao)) {
            $mensagem = "Código de verificação é obrigatório.";
            $tipo_mensagem = "error";
        } elseif (empty($nova_senha) || empty($confirmar_senha)) {
            $mensagem = "Nova senha e confirmação são obrigatórias.";
            $tipo_mensagem = "error";
        } elseif ($nova_senha !== $confirmar_senha) {
            $mensagem = "A nova senha e a confirmação não coincidem.";
            $tipo_mensagem = "error";
        } elseif (strlen($nova_senha) < 8) {
            $mensagem = "A nova senha deve ter pelo menos 8 caracteres.";
            $tipo_mensagem = "error";
        } else {
            // Verificar código
            $codigo_salvo = $_SESSION["codigo_alteracao_senha_admin"] ?? "";
            $expira_salvo = $_SESSION["codigo_alteracao_senha_admin_expira"] ?? "";
            
            if (empty($codigo_salvo) || empty($expira_salvo)) {
                $mensagem = "Nenhum código de verificação foi solicitado ou os dados são inválidos.";
                $tipo_mensagem = "error";
            } else {
                // Verificar expiração
                $agora = new DateTime();
                $expira = new DateTime($expira_salvo);
                
                if ($codigo_verificacao !== $codigo_salvo) {
                    $mensagem = "Código de verificação inválido.";
                    $tipo_mensagem = "error";
                } elseif ($agora > $expira) {
                    $mensagem = "Código de verificação expirado. Solicite novamente.";
                    $tipo_mensagem = "error";
                    // Limpar dados expirados
                    unset($_SESSION["codigo_alteracao_senha_admin"]);
                    unset($_SESSION["codigo_alteracao_senha_admin_expira"]);
                    $etapa = 1;
                } else {
                    // Alterar a senha
                    if (alterar_senha_admin($nova_senha)) {
                        $mensagem = "Senha administrativa alterada com sucesso!";
                        $tipo_mensagem = "success";
                        
                        // Limpar dados da sessão
                        unset($_SESSION["codigo_alteracao_senha_admin"]);
                        unset($_SESSION["codigo_alteracao_senha_admin_expira"]);
                        
                        // Voltar para a etapa 1
                        $etapa = 1;
                    } else {
                        $mensagem = "Erro ao alterar a senha administrativa. Verifique as permissões do arquivo.";
                        $tipo_mensagem = "error";
                    }
                }
            }
        }
    }
}

// Gerar token CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Define o título da página
$page_title = "Alterar Senha Administrativa - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Alterar Senha Administrativa</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Alterar Senha Administrativa</div>
            </div>
        </div>

        <div class="section-body">
            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo $tipo_mensagem === "success" ? "alert-success" : "alert-danger"; ?> animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <div class="form-container card shadow-sm animate__animated animate__fadeInUp">
                <div class="card-body">
                    <h2 class="form-title card-title mb-4">
                        <?php echo $etapa === 1 ? "Solicitar Código de Verificação" : "Verificar Código e Alterar Senha"; ?>
                    </h2>

                    <?php if ($etapa === 1): ?>
                        <!-- Etapa 1: Solicitar código de verificação -->
                        <form action="alterar_senha_admin.php" method="POST">
                            <!-- Campo CSRF -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                            <div class="form-group mb-3">
                                <label for="email_admin" class="form-label">Email do Administrador:</label>
                                <input type="email" name="email_admin" id="email_admin" class="form-control" value="<?php echo htmlspecialchars($_SESSION["usuario_email"] ?? ""); ?>" required>
                                <small class="form-text text-muted">Um código de verificação será enviado para este email.</small>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="painel.php" class="btn btn-secondary btn-cancel btn-hover-effect mahru-link">
                                    <i class="fas fa-arrow-left me-2"></i> Voltar
                                </a>
                                <button type="submit" name="solicitar_codigo" class="btn btn-primary btn-submit btn-hover-effect">
                                    <i class="fas fa-paper-plane me-2"></i> Solicitar Código
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Etapa 2: Verificar código e alterar senha -->
                        <form action="alterar_senha_admin.php?etapa=2" method="POST">
                            <!-- Campo CSRF -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                            <div class="form-group mb-3">
                                <label for="codigo_verificacao" class="form-label">Código de Verificação:</label>
                                <input type="text" name="codigo_verificacao" id="codigo_verificacao" class="form-control" required>
                                <small class="form-text text-muted">Digite o código enviado para seu email.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha:</label>
                                <div class="input-group">
                                    <input type="password" name="nova_senha" id="nova_senha" class="form-control" required minlength="8">
                                    <span class="input-group-text toggle-password-span" style="cursor: pointer;">
                                        <i class="fas fa-eye toggle-password"></i>
                                    </span>
                                </div>
                                <small class="form-text text-muted">Mínimo de 8 caracteres.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha:</label>
                                <div class="input-group">
                                    <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" required minlength="8">
                                    <span class="input-group-text toggle-password-span" style="cursor: pointer;">
                                        <i class="fas fa-eye toggle-password"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="alterar_senha_admin.php" class="btn btn-secondary btn-cancel btn-hover-effect mahru-link">
                                    <i class="fas fa-arrow-left me-2"></i> Voltar
                                </a>
                                <button type="submit" name="alterar_senha" class="btn btn-primary btn-submit btn-hover-effect">
                                    <i class="fas fa-key me-2"></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Add event listeners to all password toggle icons on this page
    const togglePasswordIcons = document.querySelectorAll(".toggle-password");

    togglePasswordIcons.forEach(icon => {
        icon.addEventListener("click", function() {
            // Find the corresponding input field
            const inputGroup = this.closest(".input-group");
            if (!inputGroup) return; // Safety check

            const passwordInput = inputGroup.querySelector("input[type=\"password\"], input[type=\"text\"]");
            if (!passwordInput) return; // Safety check

            // Toggle the input type between "password" and "text"
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);

            // Toggle the icon class between "fa-eye" and "fa-eye-slash"
            this.classList.toggle("fa-eye");
            this.classList.toggle("fa-eye-slash");
        });
    });
});
</script>
