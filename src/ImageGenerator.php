<?php


namespace NicoVerbruggen\ImageGenerator;

use NicoVerbruggen\ImageGenerator\Converters\HexConverter;
use NicoVerbruggen\ImageGenerator\Helpers\ColorHelper;
use GDText\Box;
use GDText\Color;

class ImageGenerator
{
    /**
     * @param string $targetSize: The target size for generated images.
     * @param string $textColorHex: The default text color for generated images. If set to null, will result in the best contrast color to the random color.
     * @param string $backgroundColorHex: The default background color for generated images. If set to null, will generate a random color.
     * @param null $fontPath: Path to the font that needs to be used to render the text on the image. Must be a TrueType font (.ttf) for this to work.
     * @param int $fontSize: The font size to be used when a TrueType font is used. Also used to calculate the line height.
     * @param int $fallbackFontSize: Can be 1, 2, 3, 4, 5 for built-in fonts in latin2 encoding (where higher numbers corresponding to larger fonts).
     */
    public function __construct(
        public $targetSize = "200x200",
        public $textColorHex = "#333",
        public $backgroundColorHex = "#EEE",
        public $fontPath = null,
        public $fontSize = 12,
        public $fallbackFontSize = 5,
        public $grid = false
    ) {}

    /**
     * Generates an image; directly renders or saves a placeholder image.
     * This will always be PNG.
     *
     * @param string $text: The text that should be rendered on the placeholder.
     * If left empty (""), will render the default size of the image.
     * If null, won't render any text.
     *
     * @param null|string $path: The path where the image needs to be stored.
     * If null, will directly output the image.
     *
     * @param null|string $size: The target size of the image that will be rendered.
     * For example: "100x100" is a valid size.
     * This value, if set, replaces the default value set in the renderer.
     *
     * @param null $bgHex: The background color for the image.
     * Must be a string with a hex value. For example: "EEE" and "#EEE" are valid.
     * This value, if set, replaces the default value set in the renderer.
     *
     * @param null $fgHex: The foreground color for the text, if applicable.
     * Must be a string with a hex value. For example: "EEE" and "#EEE" are valid.
     * This value, if set, replaces the default value set in the renderer.
     *
     * @return bool
     */
    public function generate($text = "", $path = null, $size = null, $bgHex = null, $fgHex = null): bool
    {
        // The target size is either the one set in the class or the override
        $targetSize = empty($size) ? $this->targetSize : $size;

        // Extract the dimensions from the target size
        $dimensions = explode('x', $targetSize);

        // Generate an image resource with GD
        $imageResource = imagecreatetruecolor($dimensions[0], $dimensions[1]);

        $bgHex = $bgHex ?? $this->backgroundColorHex;
        $fgHex = $fgHex ?? $this->textColorHex;

        // Determine which background + foreground (text) color needs to be used
        $bgColor = !empty($bgHex) ? $bgHex : ColorHelper::randomHex();
        $fgColor = !empty($fgHex) ? $fgHex : ColorHelper::contrastColor($bgHex);

        $allocatedBgColor = HexConverter::allocate($imageResource, $bgColor);
        imagefill($imageResource, 0, 0, $allocatedBgColor);

        if ($text == "") {
            $texttouse = $targetSize;
        } else {
            $texttouse = $text . "\n" . $targetSize;
        }

        // Merely allocating the color is enough for the background
        HexConverter::allocate($imageResource, $bgColor);

        // We'll need to use the foreground color later, so assign it to a variable
        $allocatedFgColor = HexConverter::allocate($imageResource, $fgColor);

        // Check if grid is enabled
        if (is_array($this->grid)) {

            // Extract grid configuration or set defaults
            $gridColor = $this->grid['color'] ?? $this->textColorHex; // Default grid color
            $gridSpacingX = $this->grid['spacingX'] ?? 100; // Default horizontal spacing
            $gridSpacingY = $this->grid['spacingY'] ?? 100; // Default vertical spacing

            $allocatedGridColor = HexConverter::allocate($imageResource, $gridColor);

            // Draw horizontal grid lines
            for ($y = 0; $y < $dimensions[1]; $y += $gridSpacingY) {
                imageline($imageResource, 0, $y, $dimensions[0], $y, $allocatedGridColor);
            }

            // Draw vertical grid lines
            for ($x = 0; $x < $dimensions[0]; $x += $gridSpacingX) {
                imageline($imageResource, $x, 0, $x, $dimensions[1], $allocatedGridColor);
            }
        }

        if ($this->fontPath !== null && file_exists($this->fontPath)) {
            // Use the TrueType font that was referenced.
            // Generate text
            // Using GDText\Box replaces the need for imagettftext() for enhanced text positioning and styling
            $box = new Box($imageResource);
            $box->setFontFace($this->fontPath);
            $box->setFontColor(new Color($allocatedFgColor));
            $box->setFontSize($this->fontSize);
            $box->setBox(0, 0, $dimensions[0], $dimensions[1]);
            $box->setTextAlign('center', 'center');
            $box->draw($texttouse);
        } else {
            // Use GD's built-in font for fallback
            $allocatedBgColor = HexConverter::allocate($imageResource, $bgColor);
            imagefill($imageResource, 0, 0, $allocatedBgColor);
            $allocatedFgColor = HexConverter::allocate($imageResource, $fgColor);

            // Calculate text alignment for GD's built-in font
            $textWidth = imagefontwidth($this->fallbackFontSize) * strlen($texttouse);
            $textHeight = imagefontheight($this->fallbackFontSize);
            $x = ($dimensions[0] - $textWidth) / 2;
            $y = ($dimensions[1] - $textHeight) / 2;

            // Adjusting for deprecation: Explicitly cast $x and $y to integers
            imagestring($imageResource, $this->fallbackFontSize, (int)$x, (int)$y, $texttouse, $allocatedFgColor);
        }

        // Render image with name based on the target size
        if ($path == null) {

            $filename = !empty($text) ? preg_replace('/[^a-z0-9]/i', '_', $text) . '-' . $targetSize : $targetSize;

            header('Content-type: image/png');
            header('Content-Disposition: inline; filename="'. $filename .'.png"');

            echo imagepng($imageResource, null);
            imagedestroy($imageResource);
            exit;
        } else {
            imagepng($imageResource, $path);
            imagedestroy($imageResource);
            return true;
        }
    }

    /**
     * @deprecated: Use `generate` instead.
     * @return bool
     */
    public function makePlaceholderImage($text = "", $path = null, $size = null, $bgHex = null, $fgHex = null): bool
    {
        return $this->makePlaceholderImage($text, $path, $size, $bgHex, $fgHex);
    }
}
