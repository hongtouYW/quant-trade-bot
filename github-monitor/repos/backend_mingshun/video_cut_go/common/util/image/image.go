package image

import (
	"encoding/base64"
	"errors"
	"fmt"
	"github.com/kolesa-team/go-webp/encoder"
	"github.com/kolesa-team/go-webp/webp"
	"golang.org/x/image/draw"
	webpN "golang.org/x/image/webp"
	"image"
	"image/jpeg"
	"image/png"
	"os"
	"path/filepath"
	"strings"
)

func Base64ToPng(base64, path string) error {
	ext := filepath.Ext(path)
	if ext == "" {
		path += ".png"
	} else if ext != ".png" {
		return errors.New("path extension not support")
	}

	imageData, err := extractImageData(base64)
	if err != nil {
		return err
	}

	img, err := decodeBase64ToImage(imageData)
	if err != nil {
		return err
	}

	return saveImageToFile(img, path)
}

func extractImageData(base64String string) ([]byte, error) {
	data := strings.SplitN(base64String, ",", 2)
	if len(data) != 2 {
		return nil, fmt.Errorf("invalid base64 string format")
	}

	return base64.StdEncoding.DecodeString(data[1])
}

// decodeBase64ToImage decodes the base64 image data and returns the image.Image.
func decodeBase64ToImage(data []byte) (image.Image, error) {
	img, _, err := image.Decode(strings.NewReader(string(data)))
	return img, err
}

// saveImageToFile saves the image to a file.
func saveImageToFile(img image.Image, filename string) error {
	if err := os.MkdirAll(filepath.Dir(filename), 0750); err != nil {
		return err
	}

	file, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer file.Close()

	return png.Encode(file, img)
}

func ResizeByScale(inputPath, outputPath string, videoHeight, scale int) error {
	inputFile, err := os.Open(inputPath)
	if err != nil {
		return err
	}
	defer inputFile.Close()

	img, _, err := image.Decode(inputFile)
	if err != nil {
		return err
	}

	imgSize := img.Bounds().Size()
	newHeight := videoHeight / scale
	newWidth := newHeight * imgSize.X / imgSize.Y

	dstImg := image.NewRGBA(image.Rect(0, 0, newWidth, newHeight))
	draw.CatmullRom.Scale(dstImg, dstImg.Bounds(), img, img.Bounds(), draw.Over, nil)

	outputFile, err := os.Create(outputPath)
	if err != nil {
		return err
	}
	defer outputFile.Close()

	err = png.Encode(outputFile, dstImg)
	if err != nil {
		return err
	}

	return nil
}

func Resize(inputPath, outputPath string, newHeight, newWidth int) error {
	inputFile, err := os.Open(inputPath)
	if err != nil {
		return err
	}
	defer inputFile.Close()

	img, _, err := image.Decode(inputFile)
	if err != nil {
		return err
	}

	dstImg := image.NewRGBA(image.Rect(0, 0, newWidth, newHeight))
	draw.CatmullRom.Scale(dstImg, dstImg.Bounds(), img, img.Bounds(), draw.Over, nil)

	outputFile, err := os.Create(outputPath)
	if err != nil {
		return err
	}
	defer outputFile.Close()

	err = jpeg.Encode(outputFile, dstImg, nil)
	if err != nil {
		return err
	}

	return nil
}

func PngToJpg(input, output string) (err error) {
	if filepath.Ext(output) != ".jpg" {
		return errors.New("output of PngToJpg must be .jpg")
	}

	defer func() {
		if err == nil {
			_ = os.Remove(input)
		}
	}()
	// Open the input PNG file
	pngFile, err := os.Open(input)
	if err != nil {
		return err
	}
	defer pngFile.Close()

	pngImage, err := png.Decode(pngFile)
	if err != nil {
		return err
	}

	// Create the output JPG file
	jpgFile, err := os.Create(output)
	if err != nil {
		return err
	}
	defer jpgFile.Close()

	// Encode the PNG image as JPG
	err = jpeg.Encode(jpgFile, pngImage, nil)
	if err != nil {
		return err
	}

	return nil
}

func JpgToPng(input, output string) (err error) {
	if filepath.Ext(output) != ".png" {
		return errors.New("output of JpgToPng must be .png")
	}

	defer func() {
		if err == nil {
			_ = os.Remove(input)
		}
	}()
	// Open the input JPG file
	jpgFile, err := os.Open(input)
	if err != nil {
		return err
	}
	defer jpgFile.Close()

	jpgImage, err := jpeg.Decode(jpgFile)
	if err != nil {
		return err
	}

	// Create the output JPG file
	pngFile, err := os.Create(output)
	if err != nil {
		return err
	}
	defer jpgFile.Close()

	// Encode the PNG image as JPG
	err = png.Encode(pngFile, jpgImage)
	if err != nil {
		return err
	}

	return nil
}

func JPGtoWebP(input, output string) error {
	if filepath.Ext(output) != ".webp" {
		return errors.New("output of JPGtoWebP must be .webp")
	}

	jpegFile, err := os.Open(input)
	if err != nil {
		return err
	}
	defer jpegFile.Close()

	jpegImage, _, err := image.Decode(jpegFile)
	if err != nil {
		return err
	}

	// Create the output WebP file
	webpFile, err := os.Create(output)
	if err != nil {
		return err
	}
	defer webpFile.Close()

	// Encode the JPEG image as WebP
	options, err := encoder.NewLossyEncoderOptions(encoder.PresetDefault, 100)
	if err != nil {
		return err
	}

	if err = webp.Encode(webpFile, jpegImage, options); err != nil {
		return err
	}

	return nil
}

func WebpToJpg(webpPath, output string) error {
	if filepath.Ext(output) != ".jpg" {
		return errors.New("outputPath of WebpToJpg must be .jpg")
	}

	webpFile, err := os.Open(webpPath)
	if err != nil {
		return err
	}
	defer webpFile.Close()

	img, err := webpN.Decode(webpFile)
	if err != nil {
		return err
	}

	jpgFile, err := os.Create(output)
	if err != nil {
		return err
	}
	defer jpgFile.Close()

	err = jpeg.Encode(jpgFile, img, nil)
	if err != nil {
		return err
	}

	return nil
}

func Combine(imagesPath string, output string, w, h int) error {
	imagesPath = filepath.Clean(imagesPath)
	output = filepath.Clean(output)

	files, err := os.ReadDir(imagesPath)
	if err != nil {
		return err
	}

	length := len(files)

	newCanvas := image.NewRGBA(image.Rect(0, 0, w*length, h))
	for i := 1; i <= length; i++ {
		fileName := fmt.Sprintf("%d.jpg", i)
		filePath := filepath.Join(imagesPath, fileName)

		file, err := os.Open(filePath)
		if err != nil {
			return err
		}

		defer file.Close()

		img, _, err := image.Decode(file)
		if err != nil {
			return err
		}

		drawRect := image.Rect(w*i, 0, w*(i+1), h)
		draw.Draw(newCanvas, drawRect, img, image.Point{}, draw.Over)
	}

	outputFile, err := os.Create(output)
	if err != nil {
		return err
	}
	defer outputFile.Close()

	err = jpeg.Encode(outputFile, newCanvas, nil)
	if err != nil {
		return err
	}

	return nil
}
