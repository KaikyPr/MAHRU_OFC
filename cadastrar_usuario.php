<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_nivel"] !== "admin") {
    $_SESSION["error_message"] = "Acesso negado. Apenas administradores podem cadastrar usuários.";
    header("Location: painel.php"); // Redireciona para o painel
    exit;
}

// Conexão com o banco de dados
require_once("db/conexao.php"); // Usar require_once

// Mensagens de erro/sucesso
$mensagem = "";
$tipo_mensagem = ""; // success, error

// Processar formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validar token CSRF
    if (!isset($_POST["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $mensagem = "Erro de validação CSRF. Tente novamente.";
        $tipo_mensagem = "error";
    } else {
        $nome = filter_input(INPUT_POST, "nome", FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);
        $senha = $_POST["senha"] ?? ""; // Usar null coalescing
        $nivel = filter_input(INPUT_POST, "nivel", FILTER_SANITIZE_SPECIAL_CHARS);
        $avatar_padrao = "img/img-sem-foto.jpg"; // Definir o avatar padrão

        // Validar dados
        if (empty($nome) || empty($email) || empty($senha) || empty($nivel)) {
            $mensagem = "Todos os campos são obrigatórios!";
            $tipo_mensagem = "error";
        } elseif (!$email) {
            $mensagem = "Email inválido!";
            $tipo_mensagem = "error";
        } elseif (strlen($senha) < 6) {
            $mensagem = "A senha deve ter pelo menos 6 caracteres!";
            $tipo_mensagem = "error";
        } elseif (!in_array($nivel, ["admin", "usuario", "visualizador"])) {
            $mensagem = "Nível de usuário inválido!";
            $tipo_mensagem = "error";
        } else {
            // Verificar se o email já existe
            $sql_check = "SELECT id FROM usuarios WHERE email = :email";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt_check->execute();

            if ($stmt_check->fetch()) {
                $mensagem = "Este email já está cadastrado!";
                $tipo_mensagem = "error";
            } else {
                // Hash da senha
                $hash_senha = password_hash($senha, PASSWORD_DEFAULT);

                // Inserir no banco de dados, incluindo o avatar padrão
                $sql = "INSERT INTO usuarios (nome, email, senha, nivel, avatar) VALUES (:nome, :email, :senha, :nivel, :avatar)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":nome", $nome, PDO::PARAM_STR);
                $stmt->bindParam(":email", $email, PDO::PARAM_STR);
                $stmt->bindParam(":senha", $hash_senha, PDO::PARAM_STR);
                $stmt->bindParam(":nivel", $nivel, PDO::PARAM_STR);
                $stmt->bindParam(":avatar", $avatar_padrao, PDO::PARAM_STR); // Adicionado avatar padrão

                if ($stmt->execute()) {
                    $mensagem = "Usuário cadastrado com sucesso!";
                    $tipo_mensagem = "success";
                    // Limpar formulário (opcional, pode ser feito com JS)
                    $nome = $email = $nivel = "";
                } else {
                    $mensagem = "Erro ao cadastrar usuário!";
                    $tipo_mensagem = "error";
                    error_log("Erro ao cadastrar usuário: " . print_r($stmt->errorInfo(), true));
                }
            }
        }
    }
    // Regenerar token CSRF após o POST
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
} else {
    // Gerar token CSRF inicial
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    // Limpar variáveis para formulário GET inicial
    $nome = $email = $nivel = "";
}

// Define o título da página ANTES de incluir o header
$page_title = "Cadastrar Usuário - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Cadastrar Novo Usuário</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Cadastrar Usuário</div>
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
                <h2 class="form-title card-title mb-4">Informações do Novo Usuário</h2>

                <form action="cadastrar_usuario.php" method="POST">
                    <!-- Campo CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="nome" class="form-label">Nome Completo:</label>
                                <input type="text" name="nome" id="nome" class="form-control" value="<?php echo htmlspecialchars($nome); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="senha" class="form-label">Senha:</label>
                                <div class="input-group">
                                    <input type="password" name="senha" id="senha" class="form-control" required minlength="6">
                                    <span class="input-group-text toggle-password-span" style="cursor: pointer;">
                                        <i class="fas fa-eye toggle-password"></i>
                                    </span>
                                </div>
                                <small class="form-text text-muted">Mínimo de 6 caracteres.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="nivel" class="form-label">Nível de Acesso:</label>
                                <select name="nivel" id="nivel" class="form-select" required>
                                    <option value="usuario" <?php echo ($nivel === "usuario") ? "selected" : ""; ?>>Usuário</option>
                                    <option value="admin" <?php echo ($nivel === "admin") ? "selected" : ""; ?>>Administrador</option>
                                    <option value="visualizador" <?php echo ($nivel === "visualizador") ? "selected" : ""; ?>>Visualizador</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="painel.php" class="btn btn-secondary btn-cancel btn-hover-effect mahru-link">
                            <i class="fas fa-arrow-left me-2"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary btn-submit btn-hover-effect">
                            <i class="fas fa-user-plus me-2"></i> Cadastrar Usuário
                        </button>
                    </div>
                </form>
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

