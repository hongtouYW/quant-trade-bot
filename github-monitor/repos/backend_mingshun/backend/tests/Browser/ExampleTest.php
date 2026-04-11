<?php

namespace Tests\Browser;

use Carbon\Carbon;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    /**
     * A basic browser test example.
     */
    public function testBasicExample(): void
    {
        $currentHour = Carbon::now()->hour;
        if($currentHour === 23){
            $this->browse(function (Browser $browser) {
                $todayDate = Carbon::now()->format('Y-m-d');
                $browser->visit(url('login'))
                    ->type('username', 'telegram_graph')
                    ->type('password', '123456')
                    ->press('登入');
                $browser->pause(1000);
                $browser->visit(url('chart/cutRealServerStatus'));
                $browser->pause(1000);
                $browser->screenshot('chart-' . $todayDate);
                $element = $browser->driver->findElement(WebDriverBy::cssSelector('#myChart1'));
                $elementLocation = $element->getLocation();
                $elementSize = $element->getSize();
                rename(base_path('tests/Browser/screenshots/chart-' . $todayDate . '.png'), storage_path('app/public/temp/ss-temp-chart-' . $todayDate . '.png'));
                $image = imagecreatefrompng(storage_path('app/public/temp/ss-temp-chart-' . $todayDate . '.png'));
                $cropX = $elementLocation->getX();
                $cropY = $elementLocation->getY();
                $cropWidth = $elementSize->getWidth();
                $cropHeight = $elementSize->getHeight();
                $croppedImage = imagecrop($image, ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight]);
                imagepng($croppedImage, storage_path('app/public/temp/ss-chart-' . $todayDate . '.png'));

                $browser->pause(1000);
                $browser->visit(url('chart/cutRealServerStatusSolo'));
                $browser->pause(1000);
                $browser->screenshot('chart2-' . $todayDate);
                $element = $browser->driver->findElement(WebDriverBy::cssSelector('#myChart1'));
                $elementLocation = $element->getLocation();
                $elementSize = $element->getSize();
                rename(base_path('tests/Browser/screenshots/chart2-' . $todayDate . '.png'), storage_path('app/public/temp/ss-temp-chart2-' . $todayDate . '.png'));
                $image = imagecreatefrompng(storage_path('app/public/temp/ss-temp-chart2-' . $todayDate . '.png'));
                $cropX = $elementLocation->getX();
                $cropY = $elementLocation->getY();
                $cropWidth = $elementSize->getWidth();
                $cropHeight = $elementSize->getHeight();
                $croppedImage = imagecrop($image, ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight]);
                imagepng($croppedImage, storage_path('app/public/temp/ss-chart2-' . $todayDate . '.png'));
            });
        }
    }
}
