<?php
ob_start(); // Iniciar buffer de saída BEM NO COMEÇO
if (session_status() === PHP_SESSION_NONE) { // Inicia a sessão apenas se não estiver ativa
    session_start();
}

// Define o fuso horário padrão (IMPORTANTE)
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (Mostrar durante desenvolvimento, logar em produção)
ini_set('display_errors', 1); // Mude para 0 em produção
ini_set('display_startup_errors', 1); // Mude para 0 em produção
error_reporting(E_ALL);

// Incluir o arquivo db.php (que já deve estar corrigido para buscar e salvar telefone)
require __DIR__ . '/includes/db.php';

// Constantes e Diretórios
if (!defined('DEFAULT_AVATAR')) define('DEFAULT_AVATAR', 'default.jpg'); // Certifique-se que o nome do arquivo padrão está correto
if (!defined('AVATAR_DIR')) define('AVATAR_DIR', __DIR__ . '/uploads/avatars/'); // Caminho FÍSICO no servidor

// --- Verificação de Autenticação ---
if (!isset($_SESSION['user_id'])) {
    error_log("PROFILE.PHP: Acesso negado - user_id não na sessão.");
    while (ob_get_level() > 0) { ob_end_clean(); } // Limpa buffer antes do header
    header("Location: login.php");
    exit();
}
$current_user_id = (int)$_SESSION['user_id'];
error_log("PROFILE.PHP: Acesso OK para User ID: " . $current_user_id);

// --- Obter Dados do Usuário ---
$usuario = buscarUsuarioPorId($current_user_id); // Função do db.php (corrigida)
if (!$usuario) {
    error_log("PROFILE.PHP: Usuário ID {$current_user_id} NÃO encontrado no DB. Sessão inválida?");
    session_unset(); session_destroy();
    while (ob_get_level() > 0) { ob_end_clean(); }
    header("Location: login.php?error=user_data_mismatch");
    exit();
}
error_log("PROFILE.PHP: Dados carregados User ID {$current_user_id}. Tel: " . ($usuario['telefone'] ?? 'N/A'));

// --- Processamento do Formulário (Método POST) ---
$erro = ''; $mensagem = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("PROFILE.PHP: Método POST recebido User ID: " . $current_user_id);

    // Coleta e Limpeza dos Dados
    $novo_nickname = htmlspecialchars(trim($_POST['nickname'] ?? ''), ENT_QUOTES, 'UTF-8');
    $novo_telefone = htmlspecialchars(trim($_POST['telefone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nova_chave_pix = htmlspecialchars(trim($_POST['chave_pix'] ?? ''), ENT_QUOTES, 'UTF-8');
    $tipo_chave_pix = htmlspecialchars($_POST['tipo_chave_pix'] ?? '', ENT_QUOTES, 'UTF-8');
    $avatar_atual_filename = $usuario['avatar'] ?? DEFAULT_AVATAR;

    try {
        // Validação Server-Side (PIX não obrigatório)
        if (empty($novo_nickname)) throw new Exception("O Nickname é obrigatório.");
        if (mb_strlen($novo_nickname) > 50) throw new Exception("Nickname muito longo (máx 50 caracteres).");
        if (empty($novo_telefone)) throw new Exception("O Telefone Celular é obrigatório.");
        $tel_nums = preg_replace('/\D/', '', $novo_telefone);
        if (strlen($tel_nums) < 10 || strlen($tel_nums) > 11) throw new Exception("Formato de telefone inválido.");
        // Validações de FORMATO para PIX podem ser adicionadas aqui SE valor preenchido

        $telefone_para_db = $novo_telefone; // Armazena com máscara
        $nome_arquivo_avatar = $avatar_atual_filename; // Assume o atual inicialmente

        // --- Processamento do Upload de Avatar ---
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
             error_log("PROFILE.PHP: Processando upload de avatar...");
             $file = $_FILES['avatar'];
             // 1. Diretório
             if (!file_exists(AVATAR_DIR)) { if (!@mkdir(AVATAR_DIR, 0755, true)) throw new Exception("Falha ao criar diretório de avatares."); error_log("Dir avatares criado."); }
             if (!is_writable(AVATAR_DIR)) throw new Exception("Diretório de avatares sem permissão de escrita.");
             // 2. Tamanho
             $max_size = 5 * 1024 * 1024; if ($file['size'] > $max_size) throw new Exception("Imagem muito grande (máx 5MB).");
             // 3. Tipo
             $finfo = new finfo(FILEINFO_MIME_TYPE); $mime_type = $finfo->file($file['tmp_name']); $allowed_mime = ['image/jpeg', 'image/png', 'image/gif']; if (!in_array($mime_type, $allowed_mime)) throw new Exception("Formato inválido (JPG, PNG, GIF). Tipo: {$mime_type}");
             $image_type = @exif_imagetype($file['tmp_name']); $allowed_gd_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF]; if (!in_array($image_type, $allowed_gd_types)) error_log("AVISO: exif_imagetype falhou, usando MIME {$mime_type}.");
             // 4. Extensão
             $ext = ''; switch ($image_type ?: $mime_type) { case IMAGETYPE_JPEG: case 'image/jpeg': $ext = 'jpg'; break; case IMAGETYPE_PNG: case 'image/png': $ext = 'png'; break; case IMAGETYPE_GIF: case 'image/gif': $ext = 'gif'; break; default: throw new Exception("Tipo de imagem não suportado."); }
             // 5. Nome Arquivo
             $nome_arquivo_avatar = 'avatar_' . $current_user_id . '_' . time() . '.' . $ext; $caminho_completo = AVATAR_DIR . $nome_arquivo_avatar;
             // 6. Processamento GD
             $img = null; switch ($image_type ?: $mime_type) { case IMAGETYPE_JPEG: case 'image/jpeg': $img = @imagecreatefromjpeg($file['tmp_name']); break; case IMAGETYPE_PNG: case 'image/png': $img = @imagecreatefrompng($file['tmp_name']); break; case IMAGETYPE_GIF: case 'image/gif': $img = @imagecreatefromgif($file['tmp_name']); break; }
             if (!$img) { $err=error_get_last(); error_log("Falha GD: ".($err['message']??'')); throw new Exception("Falha ao carregar imagem."); }
             $w = imagesx($img); $h = imagesy($img); $maxD = 500; if ($w <= $maxD && $h <= $maxD) { $nw=$w; $nh=$h; } elseif ($w > $h) { $nw=$maxD; $nh=floor($h*($maxD/$w)); } else { $nh=$maxD; $nw=floor($w*($maxD/$h)); }
             $res = imagecreatetruecolor($nw, $nh); if(!$res) throw new Exception("Falha imagecreatetruecolor.");
             // Transparência
             if ($ext === 'png') { imagealphablending($res, false); imagesavealpha($res, true); $trans = imagecolorallocatealpha($res, 255, 255, 255, 127); imagefilledrectangle($res, 0, 0, $nw, $nh, $trans); }
             elseif ($ext === 'gif') { $tIdx = imagecolortransparent($img); if ($tIdx >= 0 && $tIdx < imagecolorstotal($img)) { try { $tCol = imagecolorsforindex($img, $tIdx); $tNew = imagecolorallocatealpha($res, $tCol['red'], $tCol['green'], $tCol['blue'], 127); if($tNew !== false) { imagefill($res,0,0,$tNew); imagecolortransparent($res, $tNew); } } catch (ValueError $ve) { error_log("AVISO: Índice trans GIF inválido."); } } }
             if (!imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h)) throw new Exception("Falha imagecopyresampled.");
             // 7. Salvar
             $ok = false; switch($ext) { case 'jpg': $ok = imagejpeg($res, $caminho_completo, 85); break; case 'png': $ok = imagepng($res, $caminho_completo, 8); break; case 'gif': $ok = imagegif($res, $caminho_completo); break; }
             // 8. Liberar Memória
             imagedestroy($img); imagedestroy($res); if (!$ok) { error_log("Falha ao SALVAR imagem {$caminho_completo}."); throw new Exception("Falha ao salvar imagem processada."); }
             error_log("Avatar salvo: " . $caminho_completo);
             // 9. Deletar Antigo
             if ($avatar_atual_filename != DEFAULT_AVATAR && $avatar_atual_filename != $nome_arquivo_avatar) { $oldPath = AVATAR_DIR.$avatar_atual_filename; if(file_exists($oldPath)) { if(@unlink($oldPath)) error_log("Avatar antigo {$avatar_atual_filename} removido."); else error_log("AVISO: Falha ao remover avatar antigo {$avatar_atual_filename}."); } }

        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadErrors=[1=>'Tamanho excede php.ini',2=>'Tamanho excede formulário',3=>'Upload parcial',4=>'Nenhum arquivo',6=>'Pasta temp ausente',7=>'Falha escrita',8=>'Extensão PHP parou']; $errCode=$_FILES['avatar']['error']; $errMsg=$uploadErrors[$errCode]??'Erro upload'; error_log("Erro upload avatar: {$errMsg}"); $erro .= " (Aviso: falha upload avatar)";
        } else { error_log("Nenhum novo avatar enviado."); }
        // --- Fim Upload ---

        // --- Atualizar no banco ---
        if (atualizarPerfil($current_user_id, $novo_nickname, $nome_arquivo_avatar, $nova_chave_pix, $tipo_chave_pix, $telefone_para_db)) {
            error_log("Perfil DB atualizado User ID: " . $current_user_id);
            $_SESSION['user_nome'] = $novo_nickname; $_SESSION['user_avatar'] = $nome_arquivo_avatar; $_SESSION['avatar_updated'] = time();
            $_SESSION['profile_success'] = "Perfil atualizado com sucesso!";

            // Log Feed Admin
            try { if(isset($pdo)){ /* ... (código de log feed da resposta anterior) ... */ } } catch (Throwable $t) { error_log("Erro log feed: ".$t->getMessage()); }

            // Redirecionamento JS (limpo)
             while (ob_get_level() > 0) { ob_end_clean(); }
             echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Atualizando...</title></head><body><script>
                (function() {
                    const data = { userId: ".json_encode($current_user_id).", name: ".json_encode($novo_nickname).", avatar: ".json_encode($nome_arquivo_avatar).", pixKey: ".json_encode($nova_chave_pix).", pixKeyType: ".json_encode($tipo_chave_pix).", phone: ".json_encode($telefone_para_db).", timestamp: ".time()." };
                    if(typeof BroadcastChannel !== 'undefined'){try{const ch=new BroadcastChannel('profile_updates'); ch.postMessage(data); ch.close();}catch(e){console.error('BC Err:',e);}}
                    try{localStorage.setItem('profile_updated_".$current_user_id."', JSON.stringify(data));}catch(e){console.error('LS Err:',e);}
                    window.location.replace('profile.php?success=1&v=' + Date.now());
                })();
            </script></body></html>";
             exit();

        } else { throw new Exception("Falha ao salvar alterações no banco de dados."); }

    } catch (Exception $e) { $erro = $e->getMessage(); error_log("ERRO PROFILE POST: ".$e->getMessage()); }
}

// --- Preparação Exibição ---
if (isset($_SESSION['profile_success'])) { $mensagem = $_SESSION['profile_success']; unset($_SESSION['profile_success']); }
elseif (isset($_GET['success'])) { $mensagem = "Perfil atualizado com sucesso!"; }
$cache_buster = $_SESSION['avatar_updated'] ?? filemtime(AVATAR_DIR . ($usuario['avatar'] ?? DEFAULT_AVATAR)) ?? time();
$avatar_filename = htmlspecialchars($usuario['avatar'] ?? DEFAULT_AVATAR);
$avatar_url = '/uploads/avatars/' . $avatar_filename . '?v=' . $cache_buster;
$default_avatar_url = '/uploads/avatars/' . DEFAULT_AVATAR . '?v=' . time();

ob_end_flush(); // Envia toda a saída bufferizada
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meu Perfil - Love Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/gbZf3dY6/love-chat.webp" type="image/webp">
    <style>
        /* === CSS COMPLETO E CORRIGIDO === */
         :root {
            --primary: #ff007f; --primary-light: rgba(255, 0, 127, 0.15); --primary-dark: #d6006b;
            --dark: #1a1a1a; --darker: #121212; --darkest: #0a0a0a; --light: #e0e0e0; --lighter: #f5f5f5;
            --success: #00cc66; --success-light: rgba(0, 204, 102, 0.15); --danger: #ff3333; --danger-light: rgba(255, 51, 51, 0.15);
            --warning: #ffcc00; --info: #0099ff; --text-primary: rgba(255, 255, 255, 0.95); --text-secondary: rgba(255, 255, 255, 0.7);
            --text-tertiary: rgba(255, 255, 255, 0.5); --border-radius: 12px; --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); --gradient-primary: linear-gradient(135deg, #ff007f 0%, #ff4da6 100%);
            --gradient-success: linear-gradient(135deg, #00cc66 0%, #00e676 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; } html { scroll-behavior: smooth; }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--darker); color: var(--text-primary); min-height: 100vh; background-image: radial-gradient(circle at 25% 25%, rgba(255, 0, 127, 0.05) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(0, 204, 102, 0.05) 0%, transparent 50%); line-height: 1.6; overflow-x: hidden; }
        .profile-container { max-width: 900px; margin: 40px auto; padding: 0 20px; animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .profile-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 15px; position: relative; flex-wrap: wrap; gap: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .profile-title { font-size: 28px; font-weight: 700; background: var(--gradient-primary); -webkit-background-clip: text; background-clip: text; color: transparent; position: relative; padding-left: 15px; margin-right: auto; }
        .profile-title::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 5px; height: 70%; background: var(--gradient-primary); border-radius: 5px; }
        .back-btn { color: var(--text-primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; padding: 10px 20px; border-radius: var(--border-radius); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); transition: var(--transition); font-size: 15px; white-space: nowrap; }
        .back-btn:hover { background: rgba(255, 255, 255, 0.1); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 0, 127, 0.2); }
        .back-btn i { transition: var(--transition); } .back-btn:hover i { transform: translateX(-3px); }
        .profile-card { background: rgba(26, 26, 26, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: var(--border-radius); padding: 40px; box-shadow: var(--box-shadow); border: 1px solid rgba(255, 255, 255, 0.08); position: relative; overflow: hidden; }
        .profile-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(255, 0, 127, 0.03) 0%, transparent 50%), linear-gradient(-45deg, rgba(0, 204, 102, 0.03) 0%, transparent 50%); pointer-events: none; z-index: 0; }
        .avatar-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 40px; position: relative; }
        .avatar-label { position: relative; display: inline-block; cursor: pointer; border-radius: 50%; transition: var(--transition); width: 160px; height: 160px; }
        .avatar { display: block; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid transparent; background: linear-gradient(var(--darker), var(--darker)) padding-box, var(--gradient-primary) border-box; box-shadow: 0 10px 30px rgba(255, 0, 127, 0.3); transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; z-index: 1; }
        .avatar-label:hover .avatar { transform: scale(1.05); box-shadow: 0 15px 40px rgba(255, 0, 127, 0.4); }
        .avatar-edit-icon { position: absolute; bottom: 10px; right: 10px; background: var(--gradient-primary); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: var(--transition); z-index: 2; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); pointer-events: none; }
        .avatar-edit-icon i { color: white; font-size: 18px; transition: var(--transition); }
        .avatar-label:hover .avatar-edit-icon { transform: scale(1.1) rotate(10deg); box-shadow: 0 6px 20px rgba(255, 0, 127, 0.5); }
        #avatarUpload { display: none; }
        .form-group { margin-bottom: 30px; position: relative; z-index: 1; }
        .form-label { display: block; margin-bottom: 12px; font-weight: 600; color: var(--text-primary); font-size: 15px; letter-spacing: 0.5px; }

        /* Estilo Geral dos Inputs/Selects */
        .form-input {
            width: 100%; padding: 16px 20px; background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--border-radius);
            color: var(--text-primary); font-size: 16px; transition: var(--transition);
            font-family: 'Montserrat', sans-serif; box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
            /* REMOVIDO: appearance: none; e background-image para select */
        }
        .form-input:focus {
            border-color: var(--primary); outline: none;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2), 0 0 0 3px rgba(255, 0, 127, 0.2);
            background: rgba(255, 255, 255, 0.06);
        }
        .form-input::placeholder { color: var(--text-tertiary); opacity: 0.8; }
        .form-input[disabled] { background: rgba(255, 255, 255, 0.02); color: var(--text-secondary); cursor: not-allowed; opacity: 0.7; border-color: rgba(255, 255, 255, 0.05); }

        /* Estilo para as OPÇÕES dentro do select (mantido) */
        select.form-input option {
            background: #2a2a2a; /* Fundo escuro para opções */
            color: var(--text-primary); /* Texto claro */
            padding: 12px; /* Espaçamento interno */
        }
        select.form-input option:disabled { /* Estilo para a opção placeholder */
            color: var(--text-tertiary);
            font-style: italic;
        }

        .submit-btn { background: var(--gradient-success); color: white; border: none; padding: 18px 30px; border-radius: var(--border-radius); font-weight: 600; font-size: 16px; cursor: pointer; transition: var(--transition); width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 20px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 5px 20px rgba(0, 204, 102, 0.3); position: relative; overflow: hidden; z-index: 1; }
        .submit-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: 0.5s; }
        .submit-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0, 204, 102, 0.4); }
        .submit-btn:hover::before { left: 100%; } .submit-btn:active { transform: translateY(0); }
        .submit-btn i { transition: transform 0.5s ease; margin-right: 8px; } .submit-btn:hover i.fa-save { animation: spinSave 1s ease; }
        .submit-btn .fa-spinner { animation: spin 1.5s linear infinite; }
        @keyframes spinSave { 0% { transform: rotate(0deg); } 50% { transform: rotate(15deg) scale(1.1); } 100% { transform: rotate(0deg); } }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .message { padding: 18px 25px; margin-bottom: 30px; border-radius: var(--border-radius); font-weight: 500; text-align: left; display: flex; align-items: center; justify-content: flex-start; gap: 15px; transition: opacity 0.5s ease-out, transform 0.5s ease-out; opacity: 1; transform: translateY(0); border: 1px solid transparent; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); position: relative; z-index: 10; }
        .message i { font-size: 22px; flex-shrink: 0; margin-right: 5px; }
        .message span { flex-grow: 1; }
        .success { background: var(--success-light); color: var(--success); border-color: rgba(0, 204, 102, 0.3); }
        .error { background: var(--danger-light); color: var(--danger); border-color: rgba(255, 51, 51, 0.3); }
        .error span br { margin-bottom: 8px; display: block; }
        .fade-out { opacity: 0 !important; transform: translateY(-15px) !important; }
        .form-input.invalid-field { border-color: var(--danger) !important; background-color: var(--danger-light) !important; }
        .form-input.invalid-field:focus { box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2), 0 0 0 3px rgba(255, 51, 51, 0.2) !important; }

        /* Media Queries */
        @media (max-width: 768px) { .profile-container{padding:30px 15px; margin-top: 30px;} .profile-card{padding:30px 20px;} .profile-title{font-size:24px;} .avatar-label{width:140px;height:140px;} .avatar-edit-icon{width:35px;height:35px;bottom:8px;right:8px;} .avatar-edit-icon i{font-size:16px;} .form-input{padding:14px 16px;font-size:15px;} .submit-btn{padding:16px;font-size:15px;} }
        @media (max-width: 480px) { .profile-container{margin-top: 20px;} .profile-header{flex-direction:column;align-items:stretch;gap:20px;} .profile-title{margin-right:0;text-align:center;padding-left:0;} .profile-title::before{display:none;} .back-btn{width:100%;justify-content:center;} .avatar-label{width:120px;height:120px;} .avatar-edit-icon{width:30px;height:30px;bottom:5px;right:5px;} .avatar-edit-icon i{font-size:14px;} .profile-card{padding:25px 15px;} .form-group{margin-bottom:25px;} .form-input{padding:12px 15px;} .message{padding: 15px;} .message i { font-size: 20px; } }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Meu Perfil</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
            </a>
        </div>

        <div class="message success" id="successMessage" style="<?php echo empty($mensagem) ? 'display: none;' : 'display: flex;'; ?>">
            <?php if ($mensagem): ?><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($mensagem); ?></span><?php endif; ?>
        </div>
        <div class="message error" id="errorMessage" style="<?php echo empty($erro) ? 'display: none;' : 'display: flex;'; ?>">
            <?php if ($erro): ?><i class="fas fa-exclamation-circle"></i> <span><?php echo htmlspecialchars($erro); ?></span><?php endif; ?>
        </div>

        <div class="profile-card">
            <form method="POST" enctype="multipart/form-data" id="profileForm" novalidate>
                <div class="avatar-section">
                     <label for="avatarUpload" class="avatar-label" title="Clique para alterar o avatar">
                        <img src="<?php echo $avatar_url; ?>" class="avatar" id="avatarPreview" alt="Avatar Atual" onerror="this.onerror=null; this.src='<?php echo $default_avatar_url; ?>';">
                        <span class="avatar-edit-icon"><i class="fas fa-camera"></i></span>
                    </label>
                    <input type="file" id="avatarUpload" name="avatar" accept="image/jpeg, image/png, image/gif" style="display: none;">
                </div>

                <div class="form-group">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <input type="text" id="nome_completo" class="form-input" value="<?php echo htmlspecialchars($usuario['nome_completo'] ?? 'Não informado'); ?>" disabled title="Nome completo não editável.">
                </div>
                <div class="form-group">
                    <label for="nickname" class="form-label">Nickname <span style="color: var(--primary); font-weight: bold;">*</span></label>
                    <input type="text" id="nickname" name="nickname" class="form-input" value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" maxlength="50" required placeholder="Seu apelido visível">
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" class="form-input" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" disabled title="E-mail de login não editável.">
                </div>
                <div class="form-group">
                    <label for="telefone" class="form-label">Telefone Celular <span style="color: var(--primary); font-weight: bold;">*</span></label>
                    <input type="tel" id="telefone" name="telefone" class="form-input" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000" maxlength="15" required>
                </div>
                <div class="form-group">
                    <label for="tipo_chave_pix" class="form-label">Tipo de Chave PIX</label>
                    <select id="tipo_chave_pix" name="tipo_chave_pix" class="form-input">
                        <option value="" <?php echo empty($usuario['tipo_chave_pix']) ? 'selected' : ''; ?> disabled>-- Selecione se desejar --</option>
                        <option value="CPF" <?php echo (($usuario['tipo_chave_pix'] ?? '') === 'CPF') ? 'selected' : ''; ?>>CPF</option>
                        <option value="CNPJ" <?php echo (($usuario['tipo_chave_pix'] ?? '') === 'CNPJ') ? 'selected' : ''; ?>>CNPJ</option>
                        <option value="EMAIL" <?php echo (($usuario['tipo_chave_pix'] ?? '') === 'EMAIL') ? 'selected' : ''; ?>>E-mail</option>
                        <option value="TELEFONE" <?php echo (($usuario['tipo_chave_pix'] ?? '') === 'TELEFONE') ? 'selected' : ''; ?>>Telefone Celular</option>
                        <option value="ALEATORIA" <?php echo (($usuario['tipo_chave_pix'] ?? '') === 'ALEATORIA') ? 'selected' : ''; ?>>Chave Aleatória</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chave_pix" class="form-label">Chave PIX</label>
                    <input type="text" id="chave_pix" name="chave_pix" class="form-input" value="<?php echo htmlspecialchars($usuario['chave_pix'] ?? ''); ?>" placeholder="Sua chave PIX (opcional)">
                </div>
                <div class="form-group">
                    <label class="form-label">Membro Desde</label>
                    <input type="text" class="form-input" value="<?php echo isset($usuario['data_criacao']) ? date('d/m/Y \à\s H:i', strtotime($usuario['data_criacao'])) : 'N/A'; ?>" disabled>
                </div>

                <!-- Botão Salvar Alterações - CORRIGIDO -->
                <button type="submit" id="submitButton" class="submit-btn">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>

            </form> <!-- Fim do Form -->
        </div> <!-- Fim .profile-card -->
    </div> <!-- Fim .profile-container -->

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Referências ---
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarUpload = document.getElementById('avatarUpload');
        const tipoChavePixSelect = document.getElementById('tipo_chave_pix');
        const chavePixInput = document.getElementById('chave_pix');
        const telefoneInput = document.getElementById('telefone');
        const successMessageDiv = document.getElementById('successMessage');
        const errorMessageDiv = document.getElementById('errorMessage');
        const profileForm = document.getElementById('profileForm');
        const submitButton = document.getElementById('submitButton'); // Referência ao botão (confirmado)

        // --- Avatar Preview ---
        if (avatarUpload && avatarPreview) {
            avatarUpload.addEventListener('change', function(e) {
                const file = e.target.files[0]; if (!file) return;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif']; const maxSize = 5 * 1024 * 1024;
                if (!allowedTypes.includes(file.type)) { alert('Formato inválido (JPG, PNG, GIF).'); avatarUpload.value = ''; return; } if (file.size > maxSize) { alert('Arquivo muito grande (máx 5MB).'); avatarUpload.value = ''; return; }
                const reader = new FileReader(); reader.onload = (event) => { avatarPreview.style.opacity = '0'; setTimeout(() => { avatarPreview.src = event.target.result; avatarPreview.style.opacity = '1'; }, 200); }; reader.onerror = () => { alert('Erro ao ler arquivo.'); avatarUpload.value = ''; }; reader.readAsDataURL(file);
             });
        }

        // --- Formatação Telefone ---
        function formatarTelefone(input) {
             if (!input) return; let v = input.value.replace(/\D/g,'').substring(0,11);
             if(v.length>10) v=v.replace(/^(\d{2})(\d{5})(\d{4})$/,'($1) $2-$3'); else if(v.length>6) v=v.replace(/^(\d{2})(\d{4})(\d{0,4})$/,'($1) $2-$3'); else if(v.length>2) v=v.replace(/^(\d{2})(\d*)$/,'($1) $2'); else if(v.length>0) v=v.replace(/^(\d*)$/,'($1'); input.value = v;
        }
        if (telefoneInput) { telefoneInput.addEventListener('input', () => formatarTelefone(telefoneInput)); formatarTelefone(telefoneInput); }

        // --- Formatação PIX ---
        const pixMaskDef = { CPF: '000.000.000-00', CNPJ: '00.000.000/0000-00' }; // Telefone será tratado separadamente
        const pixMaxLen = { CPF: 14, CNPJ: 18, TELEFONE: 16, EMAIL: 100, ALEATORIA: 40 }; // Telefone com +
        function applyMask(val, mask) {
             let masked = ''; let k = 0; const clean = val.replace(/\D/g, '');
             for (let i = 0; i < mask.length && k < clean.length; i++) { if (mask[i] === '0') masked += clean[k++]; else masked += mask[i]; } return masked;
        }

        // Função formatarChavePixInput CORRIGIDA
        function formatarChavePixInput() {
            if (!tipoChavePixSelect || !chavePixInput) return;
            const tipo = tipoChavePixSelect.value;
            let valor = chavePixInput.value;
            let maxL = 150; // Default alto
            let ph = 'Sua chave PIX (opcional)'; // Default placeholder

            switch(tipo) {
                case 'CPF':
                    valor = valor.replace(/\D/g,'').substring(0,11);
                    valor = applyMask(valor, pixMaskDef.CPF);
                    maxL = pixMaxLen.CPF; ph = pixMaskDef.CPF;
                    break;
                case 'CNPJ':
                    valor = valor.replace(/\D/g,'').substring(0,14);
                    valor = applyMask(valor, pixMaskDef.CNPJ);
                    maxL = pixMaxLen.CNPJ; ph = pixMaskDef.CNPJ;
                    break;
                case 'TELEFONE':
                    // Lógica CORRIGIDA para formatar telefone PIX
                    let prefixoTelefone = '';
                    if (valor.startsWith('+')) {
                        prefixoTelefone = '+';
                        valor = valor.substring(1); // Remove o '+' temporariamente
                    }
                    let numerosTelefone = valor.replace(/\D/g,'').substring(0, 11);
                    let valorFormatadoTelefone = '';
                    // Aplica máscara (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
                    if (numerosTelefone.length > 10) valorFormatadoTelefone = numerosTelefone.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
                    else if (numerosTelefone.length > 6) valorFormatadoTelefone = numerosTelefone.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
                    else if (numerosTelefone.length > 2) valorFormatadoTelefone = numerosTelefone.replace(/^(\d{2})(\d*)$/, '($1) $2');
                    else if (numerosTelefone.length > 0) valorFormatadoTelefone = numerosTelefone.replace(/^(\d*)$/, '($1');
                    // Junta prefixo e número formatado
                    valor = prefixoTelefone + valorFormatadoTelefone;
                    maxL = pixMaxLen.TELEFONE; // Usa o max length definido
                    ph = '(XX) 9XXXX-XXXX';
                    break;
                case 'EMAIL':
                    valor=valor.trim(); maxL=pixMaxLen.EMAIL; ph='seu@email.com';
                    break;
                case 'ALEATORIA':
                    valor=valor.replace(/[^a-zA-Z0-9-]/g,'').substring(0,pixMaxLen.ALEATORIA); maxL=pixMaxLen.ALEATORIA; ph='Chave aleatória';
                    break;
                default:
                    ph = 'Selecione o tipo ou deixe em branco'; break;
            }
            chavePixInput.value = valor;
            chavePixInput.maxLength = maxL;
            chavePixInput.placeholder = ph;
        }
        // Adiciona listeners e formata valor inicial
        if (tipoChavePixSelect && chavePixInput) {
            tipoChavePixSelect.addEventListener('change', ()=>{ chavePixInput.value=''; formatarChavePixInput(); });
            chavePixInput.addEventListener('input', formatarChavePixInput);
            formatarChavePixInput(); // Formata ao carregar
        }

        // --- Sumiço Mensagem Sucesso ---
        function fadeOutMsg(el) { if (!el || !el.textContent.trim() || el.style.display === 'none') return; setTimeout(() => { el.classList.add('fade-out'); el.addEventListener('transitionend', () => el.remove(), { once: true }); setTimeout(() => { if (el.parentNode) el.remove(); }, 600); }, 5000); }
        fadeOutMsg(successMessageDiv);

        // --- Validação Client-Side (PIX NÃO OBRIGATÓRIO) ---
        if (profileForm && submitButton) {
            profileForm.addEventListener('submit', function(event) {
                let isValid = true;
                const errors = [];
                const fieldsToValidate = [
                    { input: document.getElementById('nickname'), required: true, msg: 'Nickname obrigatório.' },
                    { input: telefoneInput, required: true, msg: 'Telefone obrigatório.', pattern: /^\(\d{2}\)\s\d{4,5}-\d{4}$/, patternMsg: 'Formato de telefone inválido.' },
                    { input: chavePixInput, required: false }
                 ];

                // Limpa erros
                if (errorMessageDiv) { errorMessageDiv.innerHTML = ''; errorMessageDiv.style.display = 'none'; }
                fieldsToValidate.forEach(f => { if (f.input) f.input.classList.remove('invalid-field'); });
                if(tipoChavePixSelect) tipoChavePixSelect.classList.remove('invalid-field');

                // Valida campos
                fieldsToValidate.forEach(field => {
                    if (!field.input) return;
                    const value = field.input.value.trim();
                    // Removido: field.input.style.borderColor = ''; // Não precisa limpar aqui, a classe faz isso

                    if (field.required && !value) { isValid = false; errors.push(field.msg); field.input.classList.add('invalid-field'); }
                    else if (value && field.pattern && !field.pattern.test(value)) { isValid = false; errors.push(field.patternMsg); field.input.classList.add('invalid-field'); }
                });

                // Validação específica PIX (só formato)
                if (tipoChavePixSelect && chavePixInput) {
                    const tipoPix = tipoChavePixSelect.value;
                    const valorPix = chavePixInput.value.trim();
                    if (valorPix && tipoPix) { // Só valida formato se AMBOS preenchidos
                        let formatError = null;
                         if (tipoPix === 'CPF' && valorPix.replace(/\D/g, '').length !== 11) formatError = 'CPF inválido (11 dígitos).';
                         else if (tipoPix === 'CNPJ' && valorPix.replace(/\D/g, '').length !== 14) formatError = 'CNPJ inválido (14 dígitos).';
                         else if (tipoPix === 'EMAIL' && !/^\S+@\S+\.\S+$/.test(valorPix)) formatError = 'Email PIX inválido.';
                         else if (tipoPix === 'TELEFONE' && valorPix.replace(/[^\d+]/g, '').length < 10) formatError = 'Telefone PIX inválido (mín 10 dígs).';
                         if(formatError){ isValid = false; errors.push(formatError); chavePixInput.classList.add('invalid-field'); }
                    } else if (!tipoPix && valorPix){ // Digitou chave mas não selecionou tipo
                         isValid = false; errors.push("Selecione o Tipo de Chave PIX."); tipoChavePixSelect.classList.add('invalid-field');
                    }
                }

                // Mostra erros ou loading
                if (!isValid) {
                    event.preventDefault();
                    if (errorMessageDiv && errors.length > 0) {
                        errorMessageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>Por favor, corrija os erros:<br>${errors.join('<br>')}</span>`;
                        errorMessageDiv.style.display = 'flex'; errorMessageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else if (errors.length > 0) { alert("Corrija os erros:\n" + errors.join("\n")); }
                } else {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                }
            });
        }

    }); // Fim DOMContentLoaded
    </script>
</body>
</html>