<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Conexão com o banco de dados
include('db/conexao.php');

// Define o título da página ANTES de incluir o header
$page_title = 'Visão Geral - MAHRU';

// Inclui o cabeçalho padrão (que contém a navbar e a lógica do avatar)
include('includes/header.php');

// Estatísticas para a visão geral
$total_hacks = 0;
$total_hacks_interno = 0;
$total_hacks_externo = 0;
$total_por_piso = [1 => 0, 2 => 0, 3 => 0];

// Obter estatísticas de hacks
try {
    // Total de hacks
    $stmt = $pdo->query("SELECT COUNT(*) FROM hacks");
    $total_hacks = $stmt->fetchColumn();
    
    // Total de hacks internos
    $stmt = $pdo->query("SELECT COUNT(*) FROM hacks WHERE tipo = 'interno'");
    $total_hacks_interno = $stmt->fetchColumn();
    
    // Total de hacks externos
    $stmt = $pdo->query("SELECT COUNT(*) FROM hacks WHERE tipo = 'externo'");
    $total_hacks_externo = $stmt->fetchColumn();
    
    // Total por piso
    for ($i = 1; $i <= 3; $i++) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM hacks WHERE piso = $i");
        $total_por_piso[$i] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Silenciar erros para não quebrar a visão geral
}

// Mensagens de erro/sucesso
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <?php if (isset($success_message)): ?>
            <div class="success-message animate__animated animate__fadeIn"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message animate__animated animate__fadeIn"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="welcome-message animate__animated animate__fadeInDown">
            <h2 class="welcome-title">Bem-vindo ao MAHRU, <span class="user-name-highlight"><?php echo $nome_usuario_logado; ?></span>!</h2>
            <p>Este é o sistema de Mapeamento de Hacks de Rede UNESC. Aqui você pode gerenciar todos os hacks cadastrados para os diferentes pisos da universidade.</p>
        </div>
        
        <!-- Visão Geral Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="dashboard-card text-center animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="card-title">Total de Hacks</div>
                    <div class="card-value"><?php echo $total_hacks; ?></div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="dashboard-card text-center card-interno animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="card-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="card-title">Hacks Internos</div>
                    <div class="card-value"><?php echo $total_hacks_interno; ?></div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="dashboard-card text-center card-externo animate__animated animate__fadeInUp animate__delay-3s">
                    <div class="card-icon">
                        <i class="fas fa-tree"></i>
                    </div>
                    <div class="card-title">Hacks Externos</div>
                    <div class="card-value"><?php echo $total_hacks_externo; ?></div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="dashboard-card text-center animate__animated animate__fadeInUp animate__delay-4s">
                    <div class="card-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="card-title">Nível de Acesso</div>
                    <div class="card-value"><?php echo ucfirst($nivel_usuario_logado); ?></div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="dashboard-card text-center card-piso1 animate__animated animate__fadeInUp animate__delay-5s">
                    <div class="card-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="card-title">Hacks no Piso 1</div>
                    <div class="card-value"><?php echo $total_por_piso[1]; ?></div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="dashboard-card text-center card-piso2 animate__animated animate__fadeInUp animate__delay-5s">
                    <div class="card-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="card-title">Hacks no Piso 2</div>
                    <div class="card-value"><?php echo $total_por_piso[2]; ?></div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="dashboard-card text-center card-piso3 animate__animated animate__fadeInUp animate__delay-5s">
                    <div class="card-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="card-title">Hacks no Piso 3</div>
                    <div class="card-value"><?php echo $total_por_piso[3]; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Ações Rápidas -->
        <div class="section-title mt-4 animate__animated animate__fadeIn animate__delay-6s">
            <h3>Ações Rápidas</h3>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="dashboard-card animate__animated animate__fadeInLeft animate__delay-7s">
                    <h4 class="mb-3">Gerenciar Hacks</h4>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="index.php" class="btn btn-primary me-md-2 btn-hover-effect mahru-link">
                            <i class="fas fa-th-large me-2"></i> Visualizar Todos
                        </a>
                        <?php if ($nivel_usuario_logado !== 'visualizador'): ?>
                        <a href="cadastrar_hack.php" class="btn btn-success btn-hover-effect mahru-link">
                            <i class="fas fa-plus-circle me-2"></i> Cadastrar Novo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="dashboard-card animate__animated animate__fadeInRight animate__delay-7s">
                    <h4 class="mb-3">Filtrar por Tipo</h4>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="index.php?tipo=interno" class="btn btn-info me-md-2 btn-hover-effect mahru-link">
                            <i class="fas fa-building me-2"></i> Internos
                        </a>
                        <a href="index.php?tipo=externo" class="btn btn-warning btn-hover-effect mahru-link">
                            <i class="fas fa-tree me-2"></i> Externos
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($nivel_usuario_logado === 'admin'): ?>
        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="dashboard-card animate__animated animate__fadeInUp animate__delay-8s">
                    <h4 class="mb-3">Administração</h4>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="cadastrar_usuario.php" class="btn btn-danger me-md-2 btn-hover-effect mahru-link">
                            <i class="fas fa-user-plus me-2"></i> Cadastrar Novo Usuário
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Adicionar link para o relatório de hacks -->
        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="dashboard-card animate__animated animate__fadeInUp animate__delay-8s">
                    <h4 class="mb-3">Relatórios</h4>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="gerar_relatorio_hacks.php" class="btn btn-primary me-md-2 btn-hover-effect mahru-link">
                            <i class="fas fa-file-alt me-2"></i> Relatório de Hacks por Piso
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// Inclui o rodapé padrão
include('includes/footer.php');
?>
