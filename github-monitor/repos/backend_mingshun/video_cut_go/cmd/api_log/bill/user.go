package bill

import (
	"github.com/gin-gonic/gin"
	"math"
	"net/http"
	"strconv"
	"time"
	"video_cut_go/cmd/api_log/database/model"
)

type projectInfo struct {
	Name     string  `json:"name"`
	Total    int     `json:"total"`
	Size     float64 `json:"size"`
	Price    float64 `json:"price"`
	Below2GB int     `json:"below2gb"`
}

type Bill struct {
	ProjectInfo []projectInfo `json:"projectInfo"`
	projectInfo
}

func User(c *gin.Context) {
	br := new(model.BillRequest)
	if err := c.BindJSON(br); err != nil {
		c.String(http.StatusBadRequest, err.Error())
		return
	}

	if br.StartTimestamp == 0 && br.EndTimestamp == 0 {
		date := time.Now()
		date = time.Date(date.Year(), date.Month(), date.Day(), 0, 0, 0, 0, date.Location())
		BoLM := beginOfLastMonth(date)
		EoLM := endOfLastMonth(date)

		br.StartTimestamp = BoLM.Unix()
		br.EndTimestamp = EoLM.Unix()
	}

	var (
		bill map[string]*Bill
		err  error
	)

	bill, err = allBill(br)
	if err != nil {
		c.String(http.StatusBadRequest, err.Error())
		return
	}

	result := make([]Bill, 0, len(bill))
	for _, b := range bill {
		result = append(result, *b)
	}

	c.JSON(http.StatusOK, result)
}

const (
	KB = 1000
	MB = KB * KB
	GB = MB * KB
)

func allBill(br *model.BillRequest) (map[string]*Bill, error) {
	bData, err := br.GetAllBillData()
	if err != nil {
		return nil, err
	}

	return generateBill(bData), nil
}

func generateBill(bData []model.BillData) map[string]*Bill {
	result := make(map[string]*Bill)
	pData := make(map[uint]*projectInfo, len(bData))
	for _, data := range bData {
		sizeMb := byteToMb(data.Size)
		price, b2g := calPrice(sizeMb)
		s, ok := pData[data.ProjectID]
		if ok {
			s.Total++
			s.Size += sizeMb
			s.Price += price
			s.Below2GB += b2g
		} else {
			pData[data.ProjectID] = &projectInfo{Total: 1, Size: sizeMb, Price: price, Below2GB: b2g}
		}
	}

	for id, info := range pData {
		sid := strconv.Itoa(int(id))
		p, ok := result[sid]
		if !ok {
			p = &Bill{ProjectInfo: make([]projectInfo, 0)}
			result[sid] = p
		}

		info.Size = formatSize(info.Size)
		info.Price = formatPrize(info.Price)
		p.ProjectInfo = append(p.ProjectInfo, *info)
		p.Total += info.Total
		p.Size = formatSize(p.Size + info.Size)
		p.Price = formatPrize(p.Price + info.Price)
		p.Below2GB += info.Below2GB
		p.Name = sid
	}

	return result
}

func beginOfLastMonth(date time.Time) time.Time {
	return date.AddDate(0, -1, -date.Day()+1)
}

func endOfLastMonth(date time.Time) time.Time {
	return date.AddDate(0, 0, -date.Day())
}

func formatSize(size float64) float64 {
	return math.Round(size*1000) / 1000
}

func byteToMb(size float64) float64 {
	return formatSize(size / MB)
}

func formatPrize(price float64) float64 {
	return math.Round(price*100) / 100
}

const first2GbPrice = 3.8
const everyMbPrice = 0.002

func calPrice(sizeMb float64) (float64, int /*below2G*/) {
	if sizeMb == 0 {
		return 0, 0
	}

	if sizeMb <= 2000 {
		return first2GbPrice, 1
	}

	sizeMb -= 2000
	return formatPrize(first2GbPrice + (sizeMb * everyMbPrice)), 0
}
