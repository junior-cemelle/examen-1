<?php

declare(strict_types=1);

// Única dependencia: el archivo de la librería phpqrcode (incluida en el repo)
require_once __DIR__ . '/lib/phpqrcode/qrlib.php';

/**
 * Encapsula la generación del QR usando phpqrcode (sin Composer).
 *
 * phpqrcode trabaja con "pixel_size" (tamaño de cada módulo en px)
 * y "frame_size" (margen en módulos), no con dimensiones totales.
 *
 * Conversión usada:
 *   pixel_size = round(size / 37)   ← versión QR típica ≈ 37 módulos
 *   Se aplica clamp para que esté entre 2 y 20.
 *
 * La librería solo genera PNG, lo cual es suficiente para el entorno
 * de clase y no requiere extensiones adicionales más allá de GD.
 */
class QrGenerator
{
    private const MIN_SIZE     = 100;
    private const MAX_SIZE     = 1000;
    private const DEFAULT_SIZE = 300;

    // Mapa de nivel de corrección al valor que espera phpqrcode
    private const ERROR_LEVEL_MAP = [
        'L' => QR_ECLEVEL_L,
        'M' => QR_ECLEVEL_M,
        'Q' => QR_ECLEVEL_Q,
        'H' => QR_ECLEVEL_H,
    ];

    // -----------------------------------------------------------------------
    // API pública
    // -----------------------------------------------------------------------

    /**
     * Genera un QR y devuelve sus datos listos para servir al cliente.
     *
     * @param string $content         Payload ya construido (texto, URL, wifi, geo…).
     * @param int    $size            Tamaño total aproximado en px (100–1000).
     * @param string $errorCorrection Nivel L | M | Q | H.
     * @param int    $margin          Margen en módulos (0–10, default 1).
     *
     * @return array{data: string, mimeType: string, size: int, format: string}
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function generate(
        string $content,
        int    $size            = self::DEFAULT_SIZE,
        string $errorCorrection = 'M',
        int    $margin          = 1
    ): array {
        $size            = $this->validateSize($size);
        $errorCorrection = $this->validateErrorLevel($errorCorrection);
        $margin          = $this->validateMargin($margin);

        // Convertir dimensión total en px → pixel_size de módulo
        // Un QR tiene ≈ 37 módulos de ancho en versión media
        $pixelSize = (int) round($size / 37);
        $pixelSize = max(2, min(20, $pixelSize)); // clamp 2–20

        // Capturar el PNG que phpqrcode vuelca directamente al buffer de salida
        ob_start();

        try {
            QRcode::png(
                $content,
                false,                                   // false = no escribir a archivo, output al buffer
                self::ERROR_LEVEL_MAP[$errorCorrection],
                $pixelSize,
                $margin
            );
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException(
                'Error al generar el QR: ' . $e->getMessage(),
                500,
                $e
            );
        }

        $imageData = ob_get_clean();

        if (empty($imageData)) {
            throw new RuntimeException(
                'La librería phpqrcode no produjo ningún dato de imagen. '
                . 'Verifica que la extensión GD esté habilitada.',
                500
            );
        }

        return [
            'data'     => $imageData,
            'mimeType' => 'image/png',
            'size'     => $size,
            'format'   => 'png',
        ];
    }

    // -----------------------------------------------------------------------
    // Validaciones privadas
    // -----------------------------------------------------------------------

    private function validateSize(int $size): int
    {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw new InvalidArgumentException(
                'El tamaño debe estar entre ' . self::MIN_SIZE . ' y ' . self::MAX_SIZE . ' px.'
            );
        }
        return $size;
    }

    private function validateErrorLevel(string $level): string
    {
        $level = strtoupper(trim($level));
        if (!array_key_exists($level, self::ERROR_LEVEL_MAP)) {
            throw new InvalidArgumentException(
                "Nivel de corrección de errores inválido: '{$level}'. Usa L, M, Q o H."
            );
        }
        return $level;
    }

    private function validateMargin(int $margin): int
    {
        if ($margin < 0 || $margin > 10) {
            throw new InvalidArgumentException('El margen debe estar entre 0 y 10 módulos.');
        }
        return $margin;
    }
}
