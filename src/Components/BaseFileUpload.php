<?php

namespace Joshembling\ImageOptimizer\Components;

use Closure;
use Filament\Forms\Components\BaseFileUpload as FilamentBaseFileUpload;
use Illuminate\Filesystem\FilesystemAdapter;
use Intervention\Image\ImageManagerStatic as InterventionImage;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BaseFileUpload extends FilamentBaseFileUpload
{
    protected string | Closure | null $optimize = null;

    protected int | Closure | null $quality = null;

    protected string | Closure | null $watermark = null;

    protected ?string $watermarkPosition = null;

    protected int | Closure | null $watermarkOpacity = null;

    protected int | Closure | null $watermarkOffsetX = null;

    protected int | Closure | null $watermarkOffsetY = null;

    protected int | Closure | null $resize = null;

    protected int | Closure | null $maxImageWidth = null;

    protected int | Closure | null $maxImageHeight = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saveUploadedFileUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence $exception) {
                return null;
            }

            $filename = $component->getUploadedFileNameForStorage($file);
            $optimize = $component->getOptimization();
            $quality = $component->getQuality();
            $watermark = $component->getWatermark();
            $resize = $component->getResize();
            $maxImageWidth = $component->getMaxImageWidth();
            $maxImageHeight = $component->getMaxImageHeight();

            $shouldProcess = false;
            $imageWidth = null;
            $imageHeight = null;

            if (str_contains($file->getMimeType(), 'image') && ($optimize || $resize || $maxImageWidth || $maxImageHeight)) {
                $image = InterventionImage::make($file->get());

                if ($optimize) {
                    $quality = in_array(strtolower($optimize), ['jpeg', 'jpg'], true) && is_null($quality) ? 70 : 100;
                }

                if ($maxImageWidth && $image->width() > $maxImageWidth) {
                    $shouldProcess = true;
                    $imageWidth = $maxImageWidth;
                }

                if ($maxImageHeight && $image->height() > $maxImageHeight) {
                    $shouldProcess = true;
                    $imageHeight = $maxImageHeight;
                }

                if ($resize) {
                    $shouldProcess = true;

                    if ($image->height() > $image->width()) {
                        $imageHeight = (int) round($image->height() * (1 - ($resize / 100)));
                    } else {
                        $imageWidth = (int) round($image->width() * (1 - ($resize / 100)));
                    }
                }

                if ($shouldProcess) {
                    $image->resize($imageWidth, $imageHeight, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                if ($watermark) {
                    $wm = is_string($watermark) || is_resource($watermark) ? InterventionImage::make($watermark) : $watermark;

                    $watermarkPosition = $component->getWatermarkPosition();
                    $opacity = $component->getWatermarkOpacity() ?? 75;
                    $offsetX = $component->getWatermarkOffsetX();
                    $offsetY = $component->getWatermarkOffsetY();

                    if (method_exists($wm, 'opacity')) {
                        $wm->opacity($opacity);
                    }

                    if ($offsetX === null && $offsetY === null) {
                        $image->insert($wm, $watermarkPosition ?? 'bottom-right');
                    } else {
                        $image->insert($wm, $watermarkPosition ?? 'bottom-right', $offsetX ?? 0, $offsetY ?? 0);
                    }
                }

                $binary = $optimize ? $image->encode($optimize, $quality) : $image->encode();
                $filename = self::formatFilename($filename, $optimize);

                /** @var FilesystemAdapter $disk */
                $disk = $component->getDisk();
                $path = trim($component->getDirectory() . '/' . $filename, '/');

                $options = [];

                if ($component->getVisibility() === 'public') {
                    $options = ['visibility' => 'public'];
                }

                $disk->put($path, (string) $binary, $options);

                return $path;
            }

            $storeMethod = $component->getVisibility() === 'public' ? 'storePubliclyAs' : 'storeAs';

            return $file->{$storeMethod}(
                $component->getDirectory(),
                $filename,
                $component->getDiskName(),
            );
        });
    }

    public function optimize(string | Closure | null $format, ?int $quality): static
    {
        $this->optimize = $format;

        if (! is_null($quality)) {
            $this->quality = $quality;
        }

        return $this;
    }

    public function watermark(string | Closure | null $watermark, ?string $watermarkPosition): static
    {
        $this->watermark = $watermark;
        $this->watermarkPosition = $watermarkPosition ?? 'bottom-right';

        return $this;
    }

    public function watermarkOpacity(int | Closure | null $opacity): static
    {
        $this->watermarkOpacity = $opacity;

        return $this;
    }

    public function watermarkOffset(int | Closure | null $x, int | Closure | null $y): static
    {
        $this->watermarkOffsetX = $x;
        $this->watermarkOffsetY = $y;

        return $this;
    }

    public function resize(int | Closure | null $reductionPercentage): static
    {
        $this->resize = $reductionPercentage;

        return $this;
    }

    public function maxImageWidth(int | Closure | null $width): static
    {
        $this->maxImageWidth = $width;

        return $this;
    }

    public function maxImageHeight(int | Closure | null $height): static
    {
        $this->maxImageHeight = $height;

        return $this;
    }

    public function getOptimization(): ?string
    {
        return $this->evaluate($this->optimize);
    }

    public function getQuality(): ?int
    {
        return $this->evaluate($this->quality);
    }

    public function getWatermark(): ?string
    {
        return $this->evaluate($this->watermark);
    }

    public function getWatermarkPosition(): ?string
    {
        return $this->evaluate($this->watermarkPosition);
    }

    public function getWatermarkOpacity(): ?int
    {
        return $this->evaluate($this->watermarkOpacity);
    }

    public function getWatermarkOffsetX(): ?int
    {
        return $this->evaluate($this->watermarkOffsetX);
    }

    public function getWatermarkOffsetY(): ?int
    {
        return $this->evaluate($this->watermarkOffsetY);
    }

    public function getResize(): ?int
    {
        return $this->evaluate($this->resize);
    }

    public function getMaxImageWidth(): ?int
    {
        return $this->evaluate($this->maxImageWidth);
    }

    public function getMaxImageHeight(): ?int
    {
        return $this->evaluate($this->maxImageHeight);
    }

    public static function formatFilename(string $filename, ?string $format): string
    {
        if (! $format) {
            return $filename;
        }

        $info = pathinfo($filename);
        $name = $info['filename'] ?? $filename;

        return $name . '.' . ltrim(strtolower($format), '.');
    }
}
