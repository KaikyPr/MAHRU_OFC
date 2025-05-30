<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");  // Redireciona para a página de login
    exit;
}

// Conexão com o banco de dados
include("db/conexao.php");

// Define o título da página ANTES de incluir o header
$page_title = "Visualizar Hacks - MAHRU";

// Inclui o cabeçalho padrão (que agora contém a navbar e a lógica do avatar)
include("includes/header.php"); // $nivel_usuario_logado é definido aqui

// Inicializa filtros
$piso_filter = isset($_GET["piso"]) ? (int)$_GET["piso"] : 0;
$tipo_filter = isset($_GET["tipo"]) ? $_GET["tipo"] : "";
$search_term = isset($_GET["search"]) ? $_GET["search"] : "";

// Constrói a consulta SQL com base nos filtros
$sql = "SELECT * FROM hacks WHERE 1=1";
$params = [];

if ($piso_filter > 0) {
    $sql .= " AND piso = :piso";
    $params[":piso"] = $piso_filter;
}

if (!empty($tipo_filter)) {
    $sql .= " AND tipo = :tipo";
    $params[":tipo"] = $tipo_filter;
}

if (!empty($search_term)) {
    $sql .= " AND (nome LIKE :search OR descricao LIKE :search)";
    $params[":search"] = "%$search_term%";
}

$sql .= " ORDER BY piso, tipo";

// Prepara e executa a consulta
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$hacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa os hacks por piso
$hacks_by_piso = [
    1 => ["interno" => [], "externo" => []],
    2 => ["interno" => [], "externo" => []],
    3 => ["interno" => [], "externo" => []]
];

foreach ($hacks as $hack) {
    $piso = $hack["piso"];
    $tipo = $hack["tipo"];
    if (isset($hacks_by_piso[$piso][$tipo])) {
        $hacks_by_piso[$piso][$tipo][] = $hack;
    }
}

// Mensagens de erro/sucesso
if (isset($_SESSION["success_message"])) {
    $success_message = $_SESSION["success_message"];
    unset($_SESSION["success_message"]);
}

if (isset($_SESSION["error_message"])) {
    $error_message = $_SESSION["error_message"];
    unset($_SESSION["error_message"]);
}
?>

<!-- O DOCTYPE, head, e início do body já estão no header.php -->

<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Mapeamento de Hacks de Rede UNESC</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item"><a href="painel.php">Visão Geral</a></div>
                <div class="breadcrumb-item active">Visualizar Hacks</div>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message animate__animated animate__fadeIn"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message animate__animated animate__fadeIn"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filters-container">
            <h3 class="filters-title">Filtrar Hacks</h3>
            <form class="filters-form" action="index.php" method="GET">
                <div class="filter-group">
                    <label for="piso">Piso:</label>
                    <select id="piso" name="piso" class="form-select">
                        <option value="0" <?php echo $piso_filter == 0 ? "selected" : ""; ?>>Todos</option>
                        <option value="1" <?php echo $piso_filter == 1 ? "selected" : ""; ?>>Piso 1</option>
                        <option value="2" <?php echo $piso_filter == 2 ? "selected" : ""; ?>>Piso 2</option>
                        <option value="3" <?php echo $piso_filter == 3 ? "selected" : ""; ?>>Piso 3</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="tipo">Tipo:</label>
                    <select id="tipo" name="tipo" class="form-select">
                        <option value="" <?php echo $tipo_filter == "" ? "selected" : ""; ?>>Todos</option>
                        <option value="interno" <?php echo $tipo_filter == "interno" ? "selected" : ""; ?>>Interno</option>
                        <option value="externo" <?php echo $tipo_filter == "externo" ? "selected" : ""; ?>>Externo</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search">Buscar:</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Nome ou descrição" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary btn-hover-effect mahru-link">
                        <i class="fas fa-filter me-2"></i> Filtrar
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-hover-effect mahru-link">
                        <i class="fas fa-undo me-2"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <?php if (empty($hacks)): ?>
            <div class="no-hacks-message animate__animated animate__fadeIn">
                <?php if (!empty($search_term) || $piso_filter > 0 || !empty($tipo_filter)): ?>
                    <i class="fas fa-search me-2"></i> Nenhum hack encontrado com os filtros selecionados.
                <?php else: ?>
                    <i class="fas fa-info-circle me-2"></i> Nenhum hack cadastrado ainda.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php for ($piso = 1; $piso <= 3; $piso++): ?>
                <?php 
                $has_interno = !empty($hacks_by_piso[$piso]["interno"]);
                $has_externo = !empty($hacks_by_piso[$piso]["externo"]);
                
                // Pula este piso se não tiver hacks ou se estiver filtrando por outro piso
                if (($piso_filter > 0 && $piso_filter != $piso) || (!$has_interno && !$has_externo)) {
                    continue;
                }
                ?>
                
                <div class="piso-section animate__animated animate__fadeIn">
                    <h3>Piso <?php echo $piso; ?></h3>
                    
                    <?php if ($has_interno && (empty($tipo_filter) || $tipo_filter == "interno")): ?>
                        <div class="tipo-section">
                            <h4>Interno</h4>
                            <div class="row">
                                <?php foreach ($hacks_by_piso[$piso]["interno"] as $hack): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="hack-card animate__animated animate__fadeInUp">
                                            <div class="hack-badges">
                                                <span class="badge badge-piso">Piso <?php echo $hack["piso"]; ?></span>
                                                <span class="badge badge-tipo">Interno</span>
                                            </div>
                                            <h5><?php echo htmlspecialchars($hack["nome"]); ?></h5>
                                            <p class="text-muted"><?php echo htmlspecialchars(substr($hack["descricao"], 0, 100)) . (strlen($hack["descricao"]) > 100 ? "..." : ""); ?></p>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="visualizar_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn">
                                                    <i class="fas fa-eye me-1"></i> Ver
                                                </a>
                                                <?php if ($nivel_usuario_logado !== "visualizador"): // **NOVO: Condição para exibir botões** ?>
                                                <a href="editar_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn btn-edit">
                                                    <i class="fas fa-edit me-1"></i> Editar
                                                </a>
                                                <a href="excluir_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn btn-delete" onclick="return confirm("Tem certeza que deseja excluir este hack?");">
                                                    <i class="fas fa-trash me-1"></i> Excluir
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has_externo && (empty($tipo_filter) || $tipo_filter == "externo")): ?>
                        <div class="tipo-section">
                            <h4>Externo</h4>
                            <div class="row">
                                <?php foreach ($hacks_by_piso[$piso]["externo"] as $hack): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="hack-card animate__animated animate__fadeInUp">
                                            <div class="hack-badges">
                                                <span class="badge badge-piso">Piso <?php echo $hack["piso"]; ?></span>
                                                <span class="badge badge-tipo-externo">Externo</span>
                                            </div>
                                            <h5><?php echo htmlspecialchars($hack["nome"]); ?></h5>
                                            <p class="text-muted"><?php echo htmlspecialchars(substr($hack["descricao"], 0, 100)) . (strlen($hack["descricao"]) > 100 ? "..." : ""); ?></p>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="visualizar_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn">
                                                    <i class="fas fa-eye me-1"></i> Ver
                                                </a>
                                                <?php if ($nivel_usuario_logado !== "visualizador"): // **NOVO: Condição para exibir botões** ?>
                                                <a href="editar_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn btn-edit">
                                                    <i class="fas fa-edit me-1"></i> Editar
                                                </a>
                                                <a href="excluir_hack.php?id=<?php echo $hack["id"]; ?>" class="view-btn btn-delete" onclick="return confirm("Tem certeza que deseja excluir este hack?");">
                                                    <i class="fas fa-trash me-1"></i> Excluir
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        <?php endif; ?>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include("includes/footer.php");
?>

