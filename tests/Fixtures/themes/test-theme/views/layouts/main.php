<!DOCTYPE html>
<html>
<head><title><?= $this->e($title ?? '') ?></title></head>
<body><?= $this->raw($this->yield('content')) ?></body>
</html>
