<?php
$carvist_nav_active = $carvist_nav_active ?? '';
$navActive = static function (string $key) use ($carvist_nav_active): string {
    return $carvist_nav_active === $key ? ' active' : '';
};
$carvist_container_class = 'container';
if (!empty($carvist_container_wide)) {
    $carvist_container_class .= ' container--wide';
}
?>
<div id="loadingOverlay">
    <div class="tarja-topo tarja-processando">
        <div class="spinner-topo"></div>
        <span id="loadingText">Processando...</span>
    </div>
</div>

<div class="top-bar">
    <div class="top-bar-left">
        <img src="https://play-lh.googleusercontent.com/W8D5lTcX4oAlGYHeS7QDkHK2EaOPyE2bzNtjMUkHZjvIJyrXp9QIl3eW55Jr3_H3yEaph97cX6iy_IFPwy9QtaA=w240-h480-rw" alt="Logo" class="top-bar-logo">
        <h1 class="top-bar-title">SISTEMA CARVIST</h1>
    </div>
    <div class="top-bar-right">
        <button type="button" class="theme-toggle" id="themeToggle">Alternar Tema</button>
    </div>
</div>

<div class="<?php echo htmlspecialchars($carvist_container_class, ENT_QUOTES, 'UTF-8'); ?>">

    <nav>
        <a href="index.php" class="nav-link<?php echo $navActive('matriz'); ?>">Matriz Safra</a>
        <a href="combos.php" class="nav-link<?php echo $navActive('combos'); ?>">Safra Combos</a>
        <a href="segunda_via.php" class="nav-link<?php echo $navActive('segunda_via'); ?>">Safra 2ª VIA+</a>
    </nav>
