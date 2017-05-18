<?php
    $this->other = isset($this->other) ? $this->other : $_REQUEST["id"];
?>

<h1>Other</h1>
<h2><?php echo $this->other; ?></h2>