<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// **NOVO: Verificar nível de permissão**
$nivel_usuario_logado = $_SESSION["usuario_nivel"] ?? 'visitante';
if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
    // Se não for admin nem usuário padrão, redireciona ou mostra erro
    $_SESSION['error_message'] = "Você não tem permissão para cadastrar hacks.";
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
        // **RE-VERIFICAR PERMISSÃO AQUI TAMBÉM (SEGURANÇA)**
        if ($nivel_usuario_logado !== 'admin' && $nivel_usuario_logado !== 'usuario') {
             $mensagem = "Ação não permitida para seu nível de usuário.";
             $tipo_mensagem = "error";
        } else {
            $nome = filter_input(INPUT_POST, "nome", FILTER_SANITIZE_SPECIAL_CHARS);
            $descricao = filter_input(INPUT_POST, "descricao", FILTER_SANITIZE_SPECIAL_CHARS);
            $piso = filter_input(INPUT_POST, "piso", FILTER_VALIDATE_INT);
            $tipo = filter_input(INPUT_POST, "tipo", FILTER_SANITIZE_SPECIAL_CHARS);
            $latitude = filter_input(INPUT_POST, "latitude", FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $longitude = filter_input(INPUT_POST, "longitude", FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $usuario_id_cadastro = $_SESSION['usuario_id']; // Pegar ID do usuário logado

            // Validar dados
            if (empty($nome) || empty($descricao) || $piso === false || empty($tipo)) {
                $mensagem = "Os campos Nome, Descrição, Piso e Tipo são obrigatórios!";
                $tipo_mensagem = "error";
            } elseif (!in_array($piso, [1, 2, 3])) {
                $mensagem = "Piso inválido!";
                $tipo_mensagem = "error";
            } elseif (!in_array($tipo, ["interno", "externo"])) {
                $mensagem = "Tipo inválido!";
                $tipo_mensagem = "error";
            } elseif (($latitude !== null && $longitude === null) || ($latitude === null && $longitude !== null)) {
                $mensagem = "Se fornecer Latitude, deve fornecer Longitude também, e vice-versa.";
                $tipo_mensagem = "error";
            } else {
                // Processar upload de imagem
                $caminho_imagem = null; // Inicia como null
                $erro_upload = null;

                if (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK) {
                    $imagem_tmp = $_FILES["imagem"]["tmp_name"];
                    $imagem_size = $_FILES["imagem"]["size"];
                    $imagem_nome_original = basename($_FILES["imagem"]["name"]); // Segurança: usar basename
                    $imagem_ext = strtolower(pathinfo($imagem_nome_original, PATHINFO_EXTENSION));
                    $extensoes_permitidas = ["jpg", "jpeg", "png", "gif"];
                    $tamanho_maximo_bytes = 5 * 1024 * 1024; // 5MB

                    if (!in_array($imagem_ext, $extensoes_permitidas)) {
                        $erro_upload = "Erro: Tipo de arquivo inválido (Permitidos: JPG, JPEG, PNG, GIF).";
                    } elseif ($imagem_size > $tamanho_maximo_bytes) {
                        $erro_upload = "Erro: Arquivo muito grande (Máximo: 5MB).";
                    } else {
                        $diretorio_imagem = "uploads/hacks/";
                        if (!is_dir($diretorio_imagem)) {
                            if (!mkdir($diretorio_imagem, 0755, true)) {
                                $erro_upload = "Erro crítico: Não foi possível criar diretório de uploads.";
                                error_log("Falha ao criar diretório: " . $diretorio_imagem);
                            }
                        }
                        if ($erro_upload === null && !is_writable($diretorio_imagem)) {
                             $erro_upload = "Erro crítico: Sem permissão para salvar no diretório de uploads.";
                             error_log("Diretório sem permissão de escrita: " . $diretorio_imagem);
                        }
                        if ($erro_upload === null) {
                            $novo_nome = uniqid("hack_", true) . "." . $imagem_ext; // Usar prefixo e mais entropia
                            $caminho_destino = $diretorio_imagem . $novo_nome;
                            if (move_uploaded_file($imagem_tmp, $caminho_destino)) {
                                $caminho_imagem = $caminho_destino; // Define o caminho se o upload for bem-sucedido
                            } else {
                                $erro_upload = "Erro ao mover arquivo para destino final.";
                                error_log("Falha no move_uploaded_file para: " . $caminho_destino . " a partir de " . $imagem_tmp);
                            }
                        }
                    }
                    // Se houve erro no upload, definir mensagem
                    if ($erro_upload !== null) {
                        $mensagem = $erro_upload;
                        $tipo_mensagem = "error";
                    }
                } elseif (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    // Tratar outros erros de upload
                    switch ($_FILES["imagem"]["error"]) {
                        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: $erro_upload = "Erro: O arquivo enviado excede o limite de tamanho."; break;
                        case UPLOAD_ERR_PARTIAL: $erro_upload = "Erro: O upload foi feito parcialmente."; break;
                        case UPLOAD_ERR_NO_TMP_DIR: $erro_upload = "Erro: Falta pasta temporária no servidor."; break;
                        case UPLOAD_ERR_CANT_WRITE: $erro_upload = "Erro: Falha ao escrever arquivo no disco."; break;
                        case UPLOAD_ERR_EXTENSION: $erro_upload = "Erro: Extensão PHP impediu o upload."; break;
                        default: $erro_upload = "Erro desconhecido no upload (Código: {".$_FILES["imagem"]["error"] ."})."; break;
                    }
                    $mensagem = $erro_upload;
                    $tipo_mensagem = "error";
                }

                // Somente insere se não houve erro de validação ou upload
                if ($tipo_mensagem !== "error") {
                    try {
                        // Usar NULL explicitamente se latitude/longitude não forem fornecidos
                        $latitude_db = ($latitude !== null) ? $latitude : null;
                        $longitude_db = ($longitude !== null) ? $longitude : null;
                        $imagem_db = ($caminho_imagem !== null) ? $caminho_imagem : null; // Usar null se não houver imagem

                        $sql = "INSERT INTO hacks (nome, descricao, piso, tipo, imagem, latitude, longitude, usuario_id, data_cadastro) VALUES (:nome, :descricao, :piso, :tipo, :imagem, :latitude, :longitude, :usuario_id, NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(":nome", $nome, PDO::PARAM_STR);
                        $stmt->bindParam(":descricao", $descricao, PDO::PARAM_STR);
                        $stmt->bindParam(":piso", $piso, PDO::PARAM_INT);
                        $stmt->bindParam(":tipo", $tipo, PDO::PARAM_STR);
                        $stmt->bindParam(":imagem", $imagem_db, PDO::PARAM_STR);
                        $stmt->bindParam(":latitude", $latitude_db, PDO::PARAM_STR); // PDO trata NULL corretamente
                        $stmt->bindParam(":longitude", $longitude_db, PDO::PARAM_STR);
                        $stmt->bindParam(":usuario_id", $usuario_id_cadastro, PDO::PARAM_INT);

                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = "Hack cadastrado com sucesso!";
                            header("Location: index.php"); // Redireciona para a lista após sucesso
                            exit;
                        } else {
                            $errorInfo = $stmt->errorInfo();
                            $mensagem = "Erro ao cadastrar hack no banco de dados! Detalhes: " . ($errorInfo[2] ?? "N/A");
                            $tipo_mensagem = "error";
                            error_log("Erro PDO ao cadastrar hack: " . print_r($errorInfo, true));
                            // Remover imagem se o DB falhou e a imagem foi enviada
                            if ($caminho_imagem && file_exists($caminho_imagem)) {
                                @unlink($caminho_imagem);
                            }
                        }
                    } catch (PDOException $e) {
                        $mensagem = "Erro no banco de dados ao cadastrar hack. Detalhes: " . $e->getMessage();
                        $tipo_mensagem = "error";
                        error_log("Exceção PDO ao cadastrar hack: " . $e->getMessage());
                        // Remover imagem se o DB falhou e a imagem foi enviada
                        if ($caminho_imagem && file_exists($caminho_imagem)) {
                            @unlink($caminho_imagem);
                        }
                    }
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
    $nome = $descricao = $tipo = "";
    $piso = 1;
    $latitude = $longitude = "";
}

// Define o título da página ANTES de incluir o header
$page_title = "Cadastrar Hack - MAHRU";

// Inclui o cabeçalho padrão
include("includes/header.php");
?>

<!-- Conteúdo Principal da Página -->
<div class="main-content">
    <section class="section">
        <div class="section-header animate__animated animate__fadeInDown">
            <h1>Cadastrar Novo Hack</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Cadastrar Hack</div>
            </div>
        </div>

        <div class="section-body">
            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo $tipo_mensagem === "success" ? "alert-success" : "alert-danger"; ?> animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <div class="form-container animate__animated animate__fadeInUp">
                <h2 class="form-title">Informações do Hack</h2>

                <form action="cadastrar_hack.php" method="POST" enctype="multipart/form-data" id="form-cadastro-hack">
                    <!-- Campo CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="nome" class="form-label">Nome do Hack:</label>
                                <input type="text" name="nome" id="nome" class="form-control" value="<?php echo htmlspecialchars($nome); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="piso" class="form-label">Piso:</label>
                                <select name="piso" id="piso" class="form-select" required>
                                    <option value="1" <?php echo ($piso == 1) ? "selected" : ""; ?>>Piso 1</option>
                                    <option value="2" <?php echo ($piso == 2) ? "selected" : ""; ?>>Piso 2</option>
                                    <option value="3" <?php echo ($piso == 3) ? "selected" : ""; ?>>Piso 3</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="descricao" class="form-label">Descrição:</label>
                        <textarea name="descricao" id="descricao" class="form-control" rows="4" required><?php echo htmlspecialchars($descricao); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="tipo" class="form-label">Tipo:</label>
                                <select name="tipo" id="tipo" class="form-select" required>
                                    <option value="interno" <?php echo ($tipo === "interno") ? "selected" : ""; ?>>Interno</option>
                                    <option value="externo" <?php echo ($tipo === "externo") ? "selected" : ""; ?>>Externo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="imagem" class="form-label">Imagem (Opcional):</label>
                                <input type="file" name="imagem" id="imagem" class="form-control" accept="image/jpeg, image/png, image/gif">
                                <small class="form-text text-muted">Max 5MB. Formatos: JPG, PNG, GIF.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                         <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="latitude" class="form-label">Latitude (Opcional):</label>
                                <input type="text" name="latitude" id="latitude" class="form-control" placeholder="Ex: -28.6789" value="<?php echo htmlspecialchars($latitude); ?>">
                                <small class="form-text text-muted">Para exibir no mapa.</small>
                            </div>
                        </div>
                         <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="longitude" class="form-label">Longitude (Opcional):</label>
                                <input type="text" name="longitude" id="longitude" class="form-control" placeholder="Ex: -49.3700" value="<?php echo htmlspecialchars($longitude); ?>">
                                 <small class="form-text text-muted">Para exibir no mapa.</small>
                           </div>
                        </div>
                    </div>

                    <div class="image-preview-container text-center my-4" style="display: none;">
                        <h4 class="mb-3">Pré-visualização da Imagem</h4>
                        <div class="image-preview">
                            <img id="preview-img" src="#" alt="Preview" style="max-width: 100%; max-height: 300px; border-radius: 10px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary btn-cancel btn-hover-effect mahru-link">
                            <i class="fas fa-arrow-left me-2"></i> Voltar
                        </a>
                        <button type="submit" id="btn-cadastrar" class="btn btn-primary btn-submit btn-hover-effect">
                            <i class="fas fa-save me-2"></i> Cadastrar Hack
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<!-- Modal de Confirmação para Cadastro sem Imagem -->
<div class="modal fade" id="modalSemImagem" tabindex="-1" aria-labelledby="modalSemImagemLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSemImagemLabel">Confirmação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-image fa-3x text-warning"></i>
                </div>
                <p class="text-center">Tem certeza que deseja cadastrar o hack sem imagem?</p>
                <p class="text-center text-muted">Você poderá adicionar uma imagem posteriormente na tela de edição.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirmarSemImagem" class="btn btn-primary">Sim, cadastrar</button>
            </div>
        </div>
    </div>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-cadastro-hack');
    const imagemInput = document.getElementById('imagem');
    const previewContainer = document.querySelector('.image-preview-container');
    const previewImg = document.getElementById('preview-img');
    const btnCadastrar = document.getElementById('btn-cadastrar');
    const modalSemImagem = new bootstrap.Modal(document.getElementById('modalSemImagem'));
    const btnConfirmarSemImagem = document.getElementById('confirmarSemImagem');
    let submitSemImagemConfirmado = false; // Flag para controlar submissão após modal

    // Preview da imagem
    imagemInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            previewContainer.style.display = 'none';
            previewImg.src = '#';
        }
    });

    // Lógica do modal de confirmação
    form.addEventListener('submit', function(event) {
        // Verifica se a submissão já foi confirmada pelo modal
        if (submitSemImagemConfirmado) {
            return; // Permite a submissão normal
        }

        // Verifica se o campo de imagem está vazio
        if (imagemInput.files.length === 0) {
            event.preventDefault(); // Impede a submissão padrão
            modalSemImagem.show(); // Mostra o modal
        }
        // Se tiver imagem, a submissão continua normalmente
    });

    // Confirmação do modal
    btnConfirmarSemImagem.addEventListener('click', function() {
        submitSemImagemConfirmado = true; // Define a flag
        modalSemImagem.hide(); // Esconde o modal
        form.submit(); // Submete o formulário programaticamente
    });
});
</script>

