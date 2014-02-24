 <?php
 /**
  *
  * @author Oliver Dornauf
  * @version GIT: $Id$ mature.
  * @copyright   Copyright (C) 2001 - 2014 phpshaper.COM, Inc. All rights reserved.
  * @license     GNU General Public License version 3 or later; see LICENSE.txt
  *
  * Unless required by applicable law or agreed to in writing, software
  * distributed under the License is distributed on an "AS IS" BASIS,
  * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  * See the License for the specific language governing permissions and
  *limitations under the License.
  */


 /**
  * Class Web2Printer
  * Web2Printer works online, real-time. There is no need to define a second, "hidden" page. Just put a appropriate hyperlink in your document.
  * Web2Printer generates a meaningful page footer, to preserves the origin and navigation information.
  * Usage:
  * http://www.printer-friendly.com/web2printer/print?page=/2008011945/java/web2printer.html&lnk=1&img=3
  * parameter	value 	meaning 	 constraint
  * lnk	 	 	 length == 1, numeric, between  0 and 2
  * 1	 create hyperlink xref
  * 2	 create hyperlink xref and remove get parameters
  *
  * img	 	 	 length ==1, numeric, between 0 and 4
  * 1	 supress images
  * 2	 replace images with IMAGE
  * 3	 replace images with alt="" tag
  * 4	 replace images with alt="" tag and create a image xref
  * * page	 	 page to print (relative or absolute path, _without_ hostname and port)
  */
class Web2Printer {
    //  start time
    private $timingStart = "";
    // url to site
    private $site = "";
    // url to filename to process
    private $urlToPrint = "";
    // file content
    private $content = "";
    // image processing flag
    private $clearImages = 0;
    // array with hyperlinks
    private $links = Array ( );
    // number of links
    private $linkCount = 0;
    // array with images
    private $images = Array ( );
    // number of images
    private $imageCount = 0;
    // hyperlink processing flag
    private $resolveLink = 0;
    // array with parsed meta tags
    private $metaTags = Array ( );
    // number of parsed meta tags
    private $metaCount = 0;
    // copyright string parsed from meta tags
    private $copyright = "";
    // meta tag processing flag
    private $preserveMetaTags = 0;
    // stylsheet name string
    // empty or not set: stylsheet informations supressed
    // otherwise	   : stylsheet used for printing
    private $stylesheet = "";
    // page title
    private $title = "";
    // configuration array
    private  $config;

    private $web2printerVersion = "<strong>web2printer 6</strong>";

    /**
     * CTOR, load configuration from ini file.
     * To localize w2p create a ini file "config-language.ini" where $language is the corresponding language code
     * @param $language
     */
    function __construct($language) {


        $this->config = parse_ini_file("config-".$language.".ini");

        // for undefined values, set defaults
        if (! isset ( $this->config["pageUrlHeader"])) {
            $this->config["pageUrlHeader"] = "<b>This page URL</b>:<br>";
        }
        if (! isset ( $this->config["pageXRefURLHeader"] )) {
            $this->config["pageXRefURLHeader"] = "<b>Links:</b>";
        }
        if (! isset ( $this->config["pageXRefImages"] )) {
            $this->config["pageXRefImages"] = "<b>Images:</b>";
        }
        if (! isset ( $this->config["activeLinks"] )) {
            $this->config["activeLinks"] = 0;
        }
        if (! isset ( $this->config["footer"])) {
           $this->config["footer"] = 1;
        }
        if ($this->config["footer"] == 2 && ! isset ( $this->config["customFooter"])) {
            $this->config["customFooter"] = "undefined custom footer";
        }else {

        }
    }

    /**
     * run w2p
     */
    function run() {
        $this->getArgs ();
        $this->prepare (); // format
        $this->display ();
    }

    /**
     * Extract parameters
     */
    function getArgs() {

        // start from here :-)
        $this->timingStart = explode ( ' ', microtime () );

        if (isset ( $_GET ["img"] )) {
            $this->clearImages = filter_var ( $_GET ["img"], FILTER_VALIDATE_INT );
        }

        if (isset ( $_GET ["lnk"] )) {
            $this->resolveLink = filter_var ( $_GET ["lnk"], FILTER_VALIDATE_INT );
        }
        if (isset ( $_GET ["tgs"] )) {
            $this->preserveMetaTags = filter_var ( $_GET ["tgs"], FILTER_VALIDATE_INT );
        }
        if (isset ( $_GET ["style"] )) {
            $this->stylesheet = filter_var ( $_GET ["style"], FILTER_VALIDATE_STRING );
        }

        if (isset ( $_GET ["page"] )) {
            $this->page = "/".filter_var ( $_GET ["page"], FILTER_SANITIZE_URL);
        } else {
            $this->page = "/";
        }

        if ($_SERVER["HTTPS"] == "on") {
            $this->site = "https://";
        } else {
            $this->site = "http://";
        }

        if ($_SERVER["SERVER_PORT"] != "80") {
           $this->site .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
        } else {
           $this->site .= $_SERVER["SERVER_NAME"];
        }
        // prevent fraudulent server vars
        $this->urlToPrint =  filter_var ($this->site.$this->page, FILTER_SANITIZE_URL);
    }

    /**
     * get current runtime time to display within the footer
     * @return string
     */
    function get_current() {
        $stop_time = explode ( ' ', microtime () );
        $current = $stop_time [1] - $this->timingStart [1];
        $current += $stop_time [0] - $this->timingStart [0];
        return sprintf ( "%.6f seconds", $current );
    }

    /**
     * Prepare the file content. Get the page content with curl an do some regex magic to extract the relevant
     * informations.
     */

    function prepare() {

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$this->urlToPrint);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        $plainFile = curl_exec($curl);
        curl_close($curl);
        if (! isset ( $plainFile ) || $plainFile == "") {
            die ( "could not read:" . $this->urlToPrint );
        }

        // preserve title tag
        if (preg_match ( "/(<title>)(.*)<\\/title>/i", $plainFile, $regs )) {
            $this->title = trim ( $regs [2] );
            if ($this->title == "") {
                $this->title = "web2printer generated file";
            }
        }

        // parse copyright
        if (preg_match ( "/<meta([ ]?)*name([ ]?)*\\=([ ]?)*\"copyright\"([ ]?)*content([ ]?)*\\=([ ]?)*\"([^\"])*\"/i", $plainFile, $regs )) {
            $this->copyright = preg_replace ( "/<meta([ ]?)*name([ ]?)*\\=([ ]?)*\"copyright\"([ ]?)*content([ ]?)*\\=([ ]?)*\"/i", "", $regs [0] );
            $this->copyright = substr ( $this->copyright, 0, strpos ($this->copyright, "\"" ) );
        }

        $this->getMeta ( $plainFile);

        // clean up embedded styles
        $plainFile = preg_replace ( "/style[ ]*=\"[^\"]*\"/i", "", $plainFile );

        // cut off header
        $plainFile = preg_replace ( "/<(\\/)?head[ ]*>/i", "<CUTOFFHEADER>", $plainFile );
        $start = strpos ( $plainFile, "<CUTOFFHEADER>" );
        if ($start) {
            $end = strpos ( $plainFile, "<CUTOFFHEADER>", $start + 1 );
            if ($end && $end > 0) {
                $left = substr ( $plainFile, 0, $start );
                $right = substr ( $plainFile, $start + $end + 15 );
                $plainFile = $left . $right;
            } else {
                die ( "unbalanced header tags" );
            }
        }

        $offset = 0;
        $loop = true;
        while ( $loop ) {
            $start = strpos ( $plainFile, "<!-- web2printer:start -->", $offset );
            if ($start) {
                $end = strpos ( $plainFile, "<!-- web2printer:end -->", $offset );
                if ($end) {
                    $this->content = $this->content . substr ( $plainFile, $start, $end - $start );
                } else {
                    die ( "Missing <!-- web2printer:end -->" );
                }
            } else {
                $loop = false;
            }
            $offset = $end + 1;
        } // while

        if ($this->clearImages > 0) {
            $this->clearImages ();
        }

        if ($this->resolveLink > 0) {
            $this->solveLinks ();
        }
    }
    /** image processing within html
    * 0 : leave images
    * 1 : supress image printing
    * 2 : replace images with [IMAGE]
    * 3 : replace images with alt
    */
    function clearImages() {

        $offset = 0;
        $this->content = preg_replace ( "/<img$/i", "<img", $this->content );

        switch ( $this->clearImages) {
            // leave images
            case 0 :
            break;
            // remove images
            case 1 :
                $this->content = preg_replace ( "/<img([^>]?)*>/i", "", $this->content );
            break;
            // replace images with [IMAGE]
            case 2 :
                $this->content = preg_replace ( "/<img([^>]?)*>/i", "[Image]", $this->content );
            break;
            // replace images with alt
            case 3 :
                while ( $start = strpos ( $this->content, "<img", $offset ) ) {
                    $offset = $start + 1;
                    $end = strpos ( $this->content, ">", $offset );
                    $entry = substr ( $this->content, $start, $end - $start );
                    if (preg_match ( "/alt([^=]?)*=([^\"]?)*\"([^\"]?)*/i", $entry, $regs )) {
                        $alt = preg_replace ( "/alt([^=]?)*=([^\"]?)*\"/i", "", $regs [0] );
                        $left = substr ( $this->content, 0, $start );
                        $right = substr ( $this->content, $end + 1 );
                        $subst = "<b>[Image:" . $alt . "]</b>&nbsp;";
                        $this->content = $left . $subst;
                        $this->content = $this->content . $right;
                        $offset = $end + strlen ( $subst );
                    }
                } // while
                $this->content = preg_replace ( "/<img([^>]?)*>/i", "[Image]", $this->content );
            break;
            // generate image crossreference
            case 4 :
                while ( $start = strpos ( $this->content, "<img", $offset ) ) {
                    $offset = $start + 1;
                    $end = strpos ( $this->content, ">", $offset );
                    $entry = substr ( $this->content, $start, $end - $start );
                    if (preg_match ( "/src([^=]?)*=([^\"]?)*\"([^\"]?)*/i", $entry, $regs )) {
                        $src = preg_replace ( "/src([^=]?)*=([^\"]?)*\"/i", "", $regs [0] );
                        $src = $this->normalize( $src );
                        $left = substr ( $this->content, 0, $start );
                        $right = substr ( $this->content, $end + 1 );
                        $found = false;
                        $count = 0;
                        // check if we have this image already
                        while ( $count < $this->imageCount ) {
                            if (0 == strcmp ( $this->images [$count], $src )) {
                                $found = true;
                                break;
                            }
                            ++ $count;
                        }
                        if (! $found) {
                            $this->images [$this->imageCount] = $src;
                            ++ $this->imageCount;
                        }
                        $subst = "[IMAGE No:<b>" . ($count + 1) . "</b>]";
                        $this->content = $left . $subst;
                        $this->content = $this->content . $right;
                        $offset = $end + strlen ( $subst );
                    }
                } // while
            break;
            default :
                die ( "bad parameter img !" );
        }
    }

    /**
     * Create the hyperlink x-ref
     */
    function solveLinks() {

        $offset = 0;
        $len = strlen ( $this->content );

        $this->content = preg_replace ( "/<a/i", "<a", $this->content );
        $this->content = preg_replace ( "/<\/a/i", "</a", $this->content );
        while ( $start = strpos ( $this->content, "<a", $offset ) ) {
            $offset = $start + 2;
            $end = strpos ( $this->content, "</a", $offset );
            if ($end == false) {
                die ( "html error: missing &lt;/a&gt; starting at:<br>" . substr ( $this->content, $start, 255 ) );
            }
            $entry = substr ( $this->content, $start, $end - $start + 4 );
            if (preg_match ( "/href([^=]?)*=([^\"]?)*\"([^\"]?)*/i", $entry, $regs )) {
                $link = strtolower ( preg_replace ( "/href([^=]?)*=([^\"]?)*\"/i", "", $regs [0] ) );
                if ($this->resolveLink == 2) {
                    if ($pos = strpos ( $link, "?" )) {
                        $link = substr ( $link, 0, $pos );
                    }
                }
                if (0 < strlen ( $link )) {
                    if ($this->resolveLink < 3) {
                        $count = 0;
                        $found = false;
                        // check if we have this href already
                        while ( $count < $this->linkCount ) {
                            if (0 == strcmp ( $this->links [$count], $link )) {
                                $found = true;
                                break;
                            }
                            ++ $count;
                        } // while
                        if (! $found) {
                            $this->links [$this->linkCount] = $link;
                            ++ $this->linkCount;
                        }
                        ++ $count;
                    }
                    if (0 == $this->activeLinks) {
                        $entry = preg_replace ( "/<a([^>]?)*>/i", "", $entry );
                        $entry = preg_replace ( "/<\/a([^>]?)*>/i", "", $entry );
                    }
                }
                $left = substr ( $this->content, 0, $start );
                $right = substr ( $this->content, $end );
                if ($this->resolveLink < 3) {
                    $this->content = $left . "<b>" . $entry . "[" . $count . "]</b>";
                    $this->content = $this->content . $right;
                } else {
                    $this->content = $left . $entry . $right;
                }

            } // if preg_match
            $offset = $start + strlen ( $entry );
            if ($offset >= $len) {
                break;
            }
        } // while
        return;
    }

    /**
     * Extract relevant META TAG informations (copyright)
     * @param $plainFile
     */
    function getMeta($plainFile) {

        $offset = 0;
        $end = 0;
        $plainFile = preg_replace ( "/<(:space:)*meta/i", "<meta", $plainFile );
        while ( $start = stripos ( $plainFile, "<meta", $offset ) ) {
            $offset = $start + 1;
            $end = strpos ( $plainFile, ">", $offset );
            $this->metaTags [$this->metaCount] = substr ( $plainFile, $start, $end-$start + 1 );
            if (preg_match ( "/<meta(:space:)*name(:space:)*=(:space:)*\"copyright\"/i", $this->metaTags [$this->metaCount] )) {
                $this->copyright = preg_replace ( "/<meta(:space:)name(:space:)*=(:space:)*\"copyright\"(:space:)*content(:space:)*=\"/i", "", $this->metaTags [$this->metaCount] );
                $this->copyright = substr ( $this->copyright, 0, stripos ( $this->copyright, "\"" ) );
            }
            ++ $this->metaCount;
        }
    }

    /**
     * convert an local filename to uri
     */
    function normalize($input) {

        $retval = "";
        $url = parse_url($input);
        if (isset($url['host'])) {
            if (isset($url['scheme'])) {
                $retval .= $url['scheme']."://";
            } else {
                $retval .= "http://";
            }
            $retval .= $url['host'];
        }  else {
            $retval .= $this->site;
        }
        if (isset($url['path'])) {
            $retval .= $url['path'];
        }
        return $retval;
    }

    /**
     * print the procceced file
     */

    function display() {

        echo "<html><head>";

        if ($this->preserveMetaTags == 1) {
            for($i = 0; $i < $this->metaCount; $i ++) {
                echo $this->metaTags [$i];
            }
        }

        if (strlen ( $this->stylesheet ) > 0) {
            echo "<link href=\"" . $this->stylesheet . "\" rel=\"stylesheet\" type=\"text/css\">";
        }

        switch ( $this->config["footer"]) {
            case 0 :
                $this->finalfooter = "</table></body></html>";
            break;
            case 1 :
                $this->finalfooter = "<tr><td><font size=-1><p><hr size=1 noshade>This Page was generated with " . $this->web2printerVersion . " in: " . $this->get_current () . " <a href='http://www.printer-friendly.com'>http://www.printer-friendly.com</a><br></font></p></td></tr></table></body></html>";
            break;
            case 2 :
                $this->finalfooter = "<tr><td><p><hr size=1 noshade>" . $this->config["customFooter"] . "</p></td></tr></table></body></html>";
            break;
        }

        echo "<title>" . $this->title;
        echo "</title></head><body bgcolor=#ffffff><table summary='web2printer crossreference'><tr><td>";
        echo $this->content;
        echo "</tr></td><tr><td><font size=-1>";
        $link = $this->normalize ( $this->urlToPrint );
        echo "<p><hr size=1 noshade>" . $this->config["pageUrlHeader"] . "<a href=\"" . $link . "\">" . $link;
        echo "</a></p></font></tr></td>";
        echo "<tr><td><font size=-1>";
        if ($this->linkCount > 0) {
            echo $this->config["pageXRefURLHeader"] . "<br>";
            for($i = 0; $i < $this->linkCount; $i ++) {
                $link = $this->normalize ( $this->links [$i] );
                echo "[" . ($i + 1) . "] <a href=\"" . $link . "\">" . $link . "</a><br>";
            }
            echo "</font></tr></td><tr><td><font size=-1>";
        }
        if ($this->imageCount > 0) {
            echo $this->pageXRefImages;
            for($i = 0; $i < $this->imageCount; $i ++) {
                echo "[" . ($i + 1) . "] " . $this->normalize ( $this->images [$i] ) . "<br>";
            }
        }

        echo "</font></tr></td>";
        if (strlen ( $this->copyright ) > 0) {
            echo "<tr><td><font size=-1><p><hr size=1 noshade><i>Copyright:" . $this->copyright . "</i><br></font></tr></td>";
        }
        echo $this->finalfooter;
    }
}
?>
