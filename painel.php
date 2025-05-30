<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Conexão com o banco de dados
include('db/conexao.php');

// Obter informações do usuário
$query = $pdo->query("SELECT * FROM usuarios WHERE id = '{$_SESSION['usuario_id']}'");
$usuario = $query->fetch(PDO::FETCH_ASSOC);
$nome_usuario = $usuario['nome'];
$nivel_usuario = $usuario['nivel'];
// Definir o avatar do usuário
if (isset($usuario["avatar"]) && !empty($usuario["avatar"]) && file_exists($usuario["avatar"])) {
    $avatar_usuario = $usuario["avatar"];
} else {
    $avatar_usuario = "img/img-sem-foto.jpg";
}

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

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/Design_sem_nome-removebg-preview.ico" type="image/x-icon">
    <title>Visão Geral - MAHRU</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles/style.css">
    
    <!-- Animações -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
    <!-- Overlay de carregamento removido daqui, agora está global em header.php -->

    <div id="app" class="hidden-content">
        <div class="main-wrapper">
            <!-- Navbar -->
            <div class="navbar-bg"></div>
            <nav class="navbar navbar-expand-lg main-navbar">
                <form class="form-inline me-auto">
                    <ul class="navbar-nav me-3">
                        <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg"><i class="fas fa-bars"></i></a></li>
                    </ul>
                </form>
                <ul class="navbar-nav navbar-right ms-auto">
                    <li class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle nav-link-user" data-bs-toggle="dropdown">
                            <img alt="image" src="<?php echo $avatar_usuario; ?>" class="rounded-circle me-1">
                            <div class="d-none d-lg-inline-block user-greeting">Olá, <?php echo $nome_usuario; ?></div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-title">Gerenciar Perfil</div>
                            <a href="editar-perfil.php" class="dropdown-item">
                                <i class="fas fa-user me-2"></i> Meu Perfil
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Sair
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>
            
            <!-- Sidebar -->
            <div class="main-sidebar sidebar-style-2">
                <aside id="sidebar-wrapper">
                    <!-- Removido a duplicidade, mantendo apenas uma instância do nome -->
                    <div class="sidebar-brand">
                        <a href="painel.php">MAHRU</a>
                    </div>
                    <!-- Removido completamente o sidebar-brand-sm para evitar duplicidade -->
                    
                    <ul class="sidebar-menu">
                        <li class="menu-header">Principal</li>
                        <li class="active"><a class="nav-link" href="painel.php"><i class="fas fa-fire"></i> <span>Visão Geral</span></a></li>
                        
                        <li class="menu-header">Gerenciamento</li>
                        <li><a class="nav-link" href="index.php"><i class="fas fa-th-large"></i> <span>Visualizar Hacks</span></a></li>
                        
                        <?php if ($nivel_usuario !== 'visualizador'): ?>
                        <li><a class="nav-link" href="cadastrar_hack.php"><i class="fas fa-plus-circle"></i> <span>Cadastrar Hack</span></a></li>
                        <?php endif; ?>
                        
                        <?php if ($nivel_usuario === 'admin'): ?>
                        <li class="menu-header">Administração</li>
                        <li><a class="nav-link" href="cadastrar_usuario.php"><i class="fas fa-user-plus"></i> <span>Cadastrar Usuário</span></a></li>
                        <?php endif; ?>
                        
                        <li class="menu-header">Configurações</li>
                        <li><a class="nav-link" href="editar-perfil.php"><i class="fas fa-user-cog"></i> <span>Meu Perfil</span></a></li>
                        <li><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                    </ul>
                </aside>
            </div>
            
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
                        <h2 class="welcome-title">Bem-vindo ao MAHRU, <span class="user-name-highlight"><?php echo $nome_usuario; ?></span>!</h2>
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
                                <div class="card-value"><?php echo ucfirst($nivel_usuario); ?></div>
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
                                    <?php if ($nivel_usuario !== 'visualizador'): ?>
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
                    
                    <?php if ($nivel_usuario === 'admin'): ?>
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
            
            <!-- Footer -->
            <footer class="main-footer">
                <div class="footer-left">
                </div>
                <div class="footer-right">
                    
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Script de carregamento removido daqui, agora está global em footer.php --></body>
</html>
