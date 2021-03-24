package main

import (
	"context"
	"embed"
	"encoding/json"
	"flag"
	"log"
	"math/rand"
	"net/http"
	// _ "net/http/pprof"
	"os"
	"path"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"time"
)

const Version = "v0.0.1"
const ReleaseTime = "2021.3.23"

var AllowExt = sync.Map{}

var imagesList []string
var imagesListLock = sync.RWMutex{}

//go:embed page.html
var binFileList embed.FS

var webListenAddr = flag.String("listen_addr", ":8080", "Web listening address")
var imagesPath = flag.String("img_path", "", "Picture file path directory")
var imagesMaxRetry = flag.Int("img_retry", 8, "Image not repeated attempts number")
var allowRepeatImage = flag.Bool("allow_repeat", false, "Allow duplicate pictures")
var refreshTickTime = flag.Int64("refresh_time", 20, "Automatic refresh interval of picture list (Minute)")
var refreshKey = flag.String("refresh_key", "", "Picture list manually refreshed Api, the access format is /[Key you filled in], such as /123456")

type webHandle struct {
}

func (wh webHandle) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" && r.URL.Path != "/index.html" && (*refreshKey == "" || r.URL.Path != "/"+*refreshKey) {
		// 静态文件
		var filePath string

		// 检查文件合法性
		imagesListLock.RLock()
		for _, v := range imagesList {
			if ("/" + windowsPathFix(v, 1)) == r.URL.Path {
				filePath = *imagesPath + v
				break
			}
		}
		imagesListLock.RUnlock()
		if filePath == "" {
			http.NotFound(w, r)
			return
		}

		// 检查文件是否存在
		_, err := os.Stat(filePath)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}

		http.ServeFile(w, r, filePath)
	} else {
		if *refreshKey != "" && r.URL.Path == "/"+*refreshKey {
			// 数据更新
			log.Print("Web Api Call Images List Refresh")
			go imagesListRefresh(*imagesPath)
			w.Header().Set("Content-Type", "text/html; charset=utf-8")
			// 状态信息显示
			_, _ = w.Write([]byte("<!-- Simple Image Player Go " + Version + " " + ReleaseTime + " -->"))
			// 输出html
			_, _ = w.Write([]byte("图片列表刷新任务已开始,请关注Console信息"))
			return
		}

		funcType := r.FormValue("func_type")
		if funcType == "refresh_image" {
			// 获取图片
			deviceType := r.FormValue("device_type")
			lastImageUrl := r.FormValue("now_image_url")
			imageResp := getRandomImage(deviceType, lastImageUrl)
			jsonData, err := json.Marshal(imageResp)
			if err != nil {
				http.Error(w, err.Error(), 500)
			} else {
				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write(jsonData)
			}
		} else {
			data, err := binFileList.ReadFile("page.html")
			if err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			w.Header().Set("Content-Type", "text/html; charset=utf-8")
			// 状态信息显示
			_, _ = w.Write([]byte("<!-- Simple Image Player Go " + Version + " " + ReleaseTime + " -->" + "\n"))
			// 输出html
			_, _ = w.Write(data)
		}
	}
}

func init() {
	// 设置random的随机种子
	rand.Seed(time.Now().UnixNano())

	// 加载安全的图片扩展名
	AllowExt.Store(".jpg", "jpg file")
	AllowExt.Store(".jpeg", "jpeg file")
	AllowExt.Store(".png", "png file")
	AllowExt.Store(".bmp", "bmp file")
	AllowExt.Store(".webp", "webp file")
}

func main() {
	/*
		go func() {
			ip := "0.0.0.0:6060"
			if err := http.ListenAndServe(ip, nil); err != nil {
				fmt.Printf("start pprof failed on %s\n", ip)
				os.Exit(1)
			}
		}()
	*/
	flag.Parse()
	log.Print("Simple Image Player Go ", Version, " ", ReleaseTime)

	// 参数合法性检查
	if *imagesMaxRetry <= 0 || *imagesPath == "" {
		flag.Usage()
		os.Exit(1)
	}
	if fileStat, err := os.Stat(*imagesPath); err != nil {
		log.Print("images Path Check Error: ", err.Error())
		flag.Usage()
		os.Exit(1)
	} else {
		if !fileStat.IsDir() {
			log.Print("images Path Check Error: Not Dir")
			flag.Usage()
			os.Exit(1)
		}
	}
	// 修复不带/的问题
	func(path *string) {
		pathByte := []byte(*path)
		if string(pathByte[len(pathByte)-1:]) != "\\" && string(pathByte[len(pathByte)-1:]) != "/" {
			if runtime.GOOS == "windows" {
				*path = *path + "\\"
			} else {
				*path = *path + "/"
			}
		}
	}(imagesPath)
	*imagesPath = windowsPathFix(*imagesPath, 2)

	var wg sync.WaitGroup
	ctx, cancel := context.WithCancel(context.Background())

	// Web Server
	server := http.Server{
		Addr:        *webListenAddr,
		Handler:     &webHandle{},
		ReadTimeout: 3 * time.Second,
	}
	wg.Add(1)
	go func(wg *sync.WaitGroup) {
		log.Print("Start Web Server...")
		err := server.ListenAndServe()
		if err != nil {
			log.Print("Web Server Stopped: ", err.Error())
		}
		cancel()
		wg.Done()
	}(&wg)

	// Images File Monitor
	wg.Add(1)
	go watcherImagesList(ctx, cancel, *imagesPath, &wg)

	wg.Wait()
}

// 图片文件列表监控
// 暂时没什么好的思路,目前的实现可能会对性能有一定影响
func watcherImagesList(ctx context.Context, cancel context.CancelFunc, monitorPath string, wg *sync.WaitGroup) {
	log.Print("Start Images File Monitor...")
	defer func(cancel context.CancelFunc, wg *sync.WaitGroup) {
		if err := recover(); err != nil {
			// 遇到错误退出
			log.Print("Images File Monitor Stopped: ", err)
			cancel()
			wg.Done()
		}
	}(cancel, wg)

	// 初始化文件列表
	log.Print("Start Images File List Refresh")
	imagesListRefresh(monitorPath)

	if *refreshTickTime > 0 {
		// 定时器
		ticker := time.NewTicker(time.Minute * time.Duration(*refreshTickTime))
		defer func() {
			ticker.Stop()
		}()
	RefreshLoop:
		for {
			select {
			case <-ticker.C:
				// 定时刷新
				log.Print("Start Images File List Refresh")
				imagesListRefresh(monitorPath)
			case <-ctx.Done():
				break RefreshLoop
			}
		}

	} else {
		log.Print("Automatic refresh Disabled")
		<-ctx.Done()
	}

	return
}

// 获取随机图片
func getRandomImage(deviceType string, lastImageUrl string) (imageResp map[string]interface{}) {
	imageResp = map[string]interface{}{"image_url": "", "success": false, "message": "无法获取到不重复的图片"}
	imageResp["device_type"] = deviceType
	// 文件列表尚未初始化完成
	if len(imagesList) == 0 {
		imageResp["success"] = false
		imageResp["message"] = "文件列表为空,可能是初始化尚未完成,请稍后访问"
		return
	}
	// 获取随机图片
	for i := 0; i <= *imagesMaxRetry; i++ {
		imagesListLock.RLock()
		tmpImagePath := imagesList[rand.Intn(len(imagesList))]
		imagesListLock.RUnlock()
		tmpImagePath = windowsPathFix(tmpImagePath, 1)
		if tmpImagePath == lastImageUrl {
			if *allowRepeatImage {
				imageResp["success"] = true
				imageResp["message"] = "获取成功"
				imageResp["image_url"] = tmpImagePath
				break
			}
		} else {
			imageResp["success"] = true
			imageResp["message"] = "获取成功"
			imageResp["image_url"] = tmpImagePath
			break
		}
	}
	return
}

// Windows Fix
func windowsPathFix(v string, mode int) string {
	if runtime.GOOS == "windows" {
		// Windows修复斜杠
		var fixV string
		if mode == 1 {
			fixV = strings.Replace(v, "\\", "/", -1)
		} else if mode == 2 {
			fixV = strings.Replace(v, "/", "\\", -1)
		} else {
			fixV = v
		}
		return fixV
	} else {
		return v
	}
}

// 文件刷新
func imagesListRefresh(refreshDir string) {
	var tempImagesList []string
	var tempImagesListLock sync.RWMutex
	err := filepath.Walk(refreshDir,
		func(filePath string, info os.FileInfo, err error) error {
			if err != nil {
				return err
			}
			if info.IsDir() {
				return nil
			}
			fileExt := path.Ext(filePath)
			if fileExt != "" {
				if _, ok := AllowExt.Load(fileExt); ok {
					tempImagesListLock.Lock()
					tempImagesList = append(tempImagesList, strings.Replace(filePath, *imagesPath, "", 1))
					tempImagesListLock.Unlock()
				}
			}
			return nil
		})
	if err != nil {
		log.Println("imagesListRefresh Failed: ", err.Error())
	}
	tempImagesListLock.RLock()
	imagesListLock.Lock()
	imagesList = make([]string, len(tempImagesList))
	copy(imagesList, tempImagesList)
	imagesListLock.Unlock()
	tempImagesListLock.RUnlock()
	tempImagesList = nil
	// log.Print("[Images List Debug]: ", imagesList)
	log.Print("Images File List Refreshed")
}
