<?php
    $this->title = isset($this->title) ? $this->title : "";
    $this->subtitle = isset($this->subtitle) ? $this->subtitle : "qwe";
    $this->other = isset($this->other) ? $this->other : "";
?>

<h1><?php echo $this->title; ?></h1>
<h2><?php echo $this->subtitle; ?></h2>
<?php echo $this->other; ?>