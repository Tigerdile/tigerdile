<?php

/**
 * http://alvarotrigo.com
 *  
 * This class allows to print text over a given image.
 * It needs from a TrueType font format (ttf).
 * 
 * @author alvarotrigolopez 
 * @see http://www.php.net/manual/es/ref.image.php
 */

namespace Swaggerdile\Image;

class TextPainter{
	private $img;
	private $textColor;
	private $position = array();
	private $startPosition = array();
	
	private $imagePath;
	private $text;
	private $fontFile;
	private $fontSize;
	private $format;
	
	
	/**
	 * Class Constructor 
	 * 
	 * @param string $imagePath background image path
	 * @param string $text text to print
	 * @param string $fontFile the .ttf font file (TrueType)
	 * @param integer $fontSize font size
	 * 
	 * @access public
	 */
    public function __construct($imagePath, $text, $fontFile, $fontSize,
                                $format){
    	$this->imagePath = $imagePath;
    	$this->text = $text;
    	$this->fontFile = $fontFile;
    	$this->fontSize = $fontSize;
    	
        $this->format = $format;
    	$this->setQuality();
    	$this->createImage();
		$this->setTextColor();
		$this->setPosition();
    }
    
    /**
     * Sets the text color using the RGB color scale.
     * 
     * @param integer $R red quantity
     * @param integer $G gren quantity
     * @param integer $B blue quantity
     * 
     * @access public
     */
    public function setTextColor($R=230, $G=240, $B=230, $opacity=100){
        if($opacity >= 100) {
        	$this->textColor = imagecolorallocate ($this->img, $R, $G, $B);
        } else {
        	$this->textColor = imagecolorallocatealpha ($this->img, $R, $G, $B,
                    127* ((100 - $opacity)/100));
        }
    }

    /**
     * Add a watermark
     *
     * This allows for the addition of multiple watermarks based
     * on class setting.
     */
    public function addWatermark()
    {
		//creates the text over the background image
		imagettftext($this->img, $this->fontSize, 0, $this->startPosition["x"], $this->startPosition["y"], $this->textColor, $this->fontFile, $this->text);
    }

    /**
     * Shows the resulting image (background image + text)
     * On the same format as the original background image.
     * 
     * @access public
     */
    public function show(){
		switch ($this->format){
			case "JPEG":
				imagejpeg($this->img, null, $this->jpegQuality);
				break;
			case "GIF":
				imagegif($this->img);
				break;
			case "WBMP":
				imagewbmp($this->img);
				break;
			case "PNG":
			default:
				imagepng($this->img, null, (int)($this->jpegQuality/10));
		}
    }
    
    /**
     * Sets the quality of the resulting JPEG image.
     * Default: 85
     * @param integer $value quality
     * @access public
     */
    public function setQuality($value=85){
    	$this->jpegQuality = $value;
    }

    /**
     * Set font size by percent
     *
     * @param integer percent
     */
    public function setFontSizePercent($perc)
    {
        $ptSize = ((imagesy($this->img) * ($perc/100))*3)/4;

        $this->setFontSize($ptSize);
    }

    /**
     * Calculates the X and Y coordinates for the desired position 
     * of the text. 
     * @param string $x x position: left, center, right or custom 
     * @param string $y y position: top, center, bottom or custom
     * @access public
     */
    public function setPosition($x="center", $y="center"){
    	$this->position["x"] = $x;
    	$this->position["y"] = $y;
    	
    	$dimensions = $this->getTextDimensions();
		
    	if($x=="left"){
			$this->startPosition["x"] = 0;
    	}
    	else if($x=="center"){    		
    		$this->startPosition["x"] = imagesx($this->img)/2 - $dimensions["width"]/2;
    	}
    	else if($x=="right"){
    		$this->startPosition["x"] = imagesx($this->img) - $dimensions["width"];
    	}
    	//custom
    	else{
    		$this->startPosition["x"] = $x;
    	}
    	
    	if($y=="top"){
    		$this->startPosition["y"] = 0 + $dimensions["heigh"];
    	}
    	else if($y=="center"){
    		$this->startPosition["y"]  = imagesy($this->img)/2 + $dimensions["heigh"]/2;
    	}
    	else if($y=="bottom"){
    		$this->startPosition["y"]  = imagesy($this->img);
    	}
    	//custom
        else{
    		$this->startPosition["y"] = $y;
    	}
    
    }

    /**
     * Create a new image to work with from the given background 
     * image.
     * Supported formats: jpeg, jpg, png, gif, wbmp
     * @access private
     */
    private function createImage(){
       	if($this->format=="JPEG"){
       		$this->img = imagecreatefromjpeg($this->imagePath);
		}
		else if($this->format=="PNG"){
			$this->img = imagecreatefrompng($this->imagePath);
		}
		else if ($this->format=="GIF"){
			$this->img = imagecreatefromgif($this->imagePath);
		}
		else if ($this->format="WBMP"){
			$this->img = imagecreatefromwbmp($this->imagePath);
		}else{
            throw new \Exception("File type not supported: {$this->format}");
		}
    }
    
    /**
     * Sets the font file for the text.
     * 
     * @param string $fontFile the .ttf font file (TrueType)
     * @access public
     */
    public function setFontFile($fontFile){
    	$this->fontFile = $fontFile;
    	
    	//recalculate the text position depending on the new font file
    	$this->setPosition($this->position["x"], $this->position["y"]);
    }
    
    /**
     * Sets the font size for the text.
     * 
     * @param integer $fontSize 
     * @access public
     */
    public function setFontSize($fontSize){
    	$this->fontSize = $fontSize;
    	
    	//recalculate the text position depending on the new font size
    	$this->setPosition($this->position["x"], $this->position["y"]);
    }
    
    /**
     * It returns the dimensions of the text to print with the given 
     * size and font.
     * 
     * @return array containing the width and height (width,heigh) of the text to print.
     * @access public
     */
    private function getTextDimensions(){
    	$dimensions = imagettfbbox($this->fontSize, 0, $this->fontFile, $this->text);
	
		$minX = min(array($dimensions[0],$dimensions[2],$dimensions[4],$dimensions[6]));
		$maxX = max(array($dimensions[0],$dimensions[2],$dimensions[4],$dimensions[6]));
		
		$minY = min(array($dimensions[1],$dimensions[3],$dimensions[5],$dimensions[7]));
		$maxY = max(array($dimensions[1],$dimensions[3],$dimensions[5],$dimensions[7]));
		
		return array(
			'width' => $maxX - $minX,
			'heigh' => $maxY - $minY
		);
    }  
}
