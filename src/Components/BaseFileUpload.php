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

    protected string | Closure | null $quality = null;

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

    public function getQuality(): ?string
    {
        return $this->evaluate($this->quality);
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
