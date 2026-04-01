# nohup python3 /opt/x18r/x18r_download_images.py &> /opt/x18r/x18r_download_images.log &

import pymysql
import pymysql.cursors
import requests
from bs4 import BeautifulSoup
import os
import time
import re
import json
import matplotlib.pyplot as plt   #pip3 install matplotlib
from matplotlib.font_manager import FontProperties
from matplotlib.pyplot import MultipleLocator
import matplotlib
import telepot #pip3 install telepot
# Import date and timedelta class
# from datetime module
from datetime import date
from datetime import timedelta


botToken = "8290212482:AAHcRhmN5B7C-LL2KoyCWXmEqAPkT2KU7O0" #baidu_bot  hongtou
chat_id = "-1003261673949"  #4s-百度统计
bot = telepot.Bot(botToken)


#四色nv
site = "sise_lajiao_yesterday_fs" # 2023-10-06
baidu_userName = "hunao135803"  #账号maomtongji  密码RWOQYiUxWahUla2X 手机 -
baidu_accessToken = "eyJhbGciOiJIUzM4NCJ9.eyJzdWIiOiJhY2MiLCJhdWQiOiLnmb7luqbnu5_orqEiLCJ1aWQiOjU0NDExOTE0LCJhcHBJZCI6IjEzYmQ1MDQ5YTY3NmQxMDczNzk1OTkzMjEwMmVjNTU3IiwiaXNzIjoi5ZWG5Lia5byA5Y-R6ICF5Lit5b-DIiwicGxhdGZvcm1JZCI6IjQ5NjAzNDU5NjU5NTg1NjE3OTQiLCJleHAiOjE3Nzc1NzIxMzUsImp0aSI6Ii03NzU4MTc0MDA4NTgyNjE5MDEwIn0.fHIf9AY75c3oMSyRfzl06Il0Y2OjaYUM9uVLRv6X4XVr70sU7_isNiLIAE2H9sbd"
expire = "token   2025-12-25 13:57:15过期"
site_id = "20355865"



# Python program to get
# Yesterday's date

# Get today's date
today = date.today()
str_today  = today.strftime('%Y%m%d')
print("Today is: ", str_today)

# Get 2 days earlier
yesterday = today - timedelta(days=1)
str_yesterday  = yesterday.strftime('%Y%m%d')
print("yesterday: ", str_yesterday)

# Get the day before yesterday earlier
bef_yesterday = today - timedelta(days=2)
str_bef_yesterday  = bef_yesterday.strftime('%Y%m%d')
print("the day before yesterday: ", str_bef_yesterday)

# Get 2 days earlier
lastweek = today - timedelta(days=7)
str_lastweek  = lastweek.strftime('%Y%m%d')
print("last week: ", str_lastweek)

# 开始画图
def plot_save(xyDataList, save_path):  # [{ "legend":legend, "xData":xData,"yData":yData},{"xData":xData,"yData":yData}]
    # 设置matplotlib正常显示中文和负号
    markers = ["*", ".", "x"]
    font = FontProperties(
        fname="/usr/local/python3/lib/python3.9/site-packages/matplotlib/mpl-data/fonts/ttf/SimHei.ttf")

    matplotlib.rcParams['font.sans-serif'] = ['SimHei']  # 用黑体显示中文
    matplotlib.rcParams['axes.unicode_minus'] = False  # 正常显示负号

    # 把x轴的刻度间隔设置为1，并存在变量里
    x_major_locator = MultipleLocator(1)
    # 把y轴的刻度间隔设置为10万，并存在变量里
    y_major_locator = MultipleLocator(50000)

    ax = plt.gca()
    # ax为两条坐标轴的实例
    ax.xaxis.set_major_locator(x_major_locator)
    # 把x轴的主刻度设置为1的倍数
    ax.yaxis.set_major_locator(y_major_locator)
    # 把y轴的主刻度设置为10万的倍数
    plt.xlim(0, 24)
    # 把x轴的刻度范围设置为-0.5到11，因为0.5不满一个刻度间隔，所以数字不会显示出来，但是能看到一点空白
    # plt.ylim(0, 400000)
    # 把y轴的刻度范围设置为-5到110，同理，-5不会标出来，但是能看到一点空白

    # plt.figure(figsize=(25, 10))

    legends = []

    for index, data in enumerate(xyDataList):
        x = data["xData"]
        y = data["yData"]
        legend = data["legend"]
        plt.plot(x, y, '', marker=markers[index], markersize=5)
        # 给图像添加注释，并设置样式
        # for a, b in zip(x, y):
        #   plt.text(a, b, b, ha='center', va='bottom', fontsize=10)
        legends.append(legend)
    title = "百度统计 -- " + site

    plt.title(title, fontproperties=font)  # 折线图标题
    plt.xlabel('时间', fontproperties=font)  # x轴标题
    plt.ylabel('IP数', fontproperties=font)  # y轴标题

    # 绘制图例
    plt.legend(legends, prop=font)
    # plt.rcParams['figure.figsize'] = (160, 20)  # 设置figure_size尺寸
    plt.rcParams['savefig.dpi'] = 300  # 图片像素
    plt.rcParams['figure.dpi'] = 300  # 分辨率
    # 保存图片
    plt.savefig(save_path)
    # 显示图像
    plt.show()

def baidu(str_date_1,str_date_2):
    api_url = "https://api.baidu.com/json/tongji/v1/ReportService/getData"
    headers = {
        "Content-Type":"application/json"
    }
    header = {
        "userName":baidu_userName,
        "accessToken":baidu_accessToken #"eyJhbGciOiJIUzM4NCJ9.eyJzdWIiOiJhY2MiLCJhdWQiOiLnmb7luqbnu5_orqEiLCJ1aWQiOjQxOTk4NTA3LCJhcHBJZCI6IjEzYmQ1MDQ5YTY3NmQxMDczNzk1OTkzMjEwMmVjNTU3IiwiaXNzIjoi5ZWG5Lia5byA5Y-R6ICF5Lit5b-DIiwicGxhdGZvcm1JZCI6IjQ5NjAzNDU5NjU5NTg1NjE3OTQiLCJleHAiOjE2ODQ3NjQ5NjksImp0aSI6Ijc0OTU1MjA4ODQyNTI5NjY5NjIifQ.F_kRbU2Ws3sbk0yNB-uD8Vmv0k7F3R2MtGr_OQj9cQ15ZUhnLOPkToPL7R-L2nPI"
    }
    body = {
        "site_id":site_id,
        "start_date":str_date_1,
        "end_date":str_date_1,
        "metrics":"ip_count",
        "method":"trend/time/a",
        "start_date2":str_date_2,
        "end_date2":str_date_2,
        "max_results":"24",
        "gran":"hour",
        "area":""
    }
    data = {
        "header":header,
        "body":body
    }
    res = requests.post(api_url, headers=headers, data=json.dumps(data))
    hours = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23]

    print("baidu res 139:")
    print(res.text)
    if "access token you provided is invalidate" in str(res.text):
        msg = "site_" + site + "百度token过期了_baidu_userName_" + baidu_userName
        print("百度token 过期了")
        tg_send_msg_url = "https://api.telegram.org/bot" + botToken + "/sendMessage?chat_id=" + chat_id + "&text=" + msg
        requests.get(tg_send_msg_url)
        return 0
    if "账户受限" in str(res.text):
        msg = "site_" + site + ",百度站点被封了_baidu_userName_" + baidu_userName
        print("百度站点被封了")
        tg_send_msg_url = "https://api.telegram.org/bot" + botToken + "/sendMessage?chat_id=" + chat_id + "&text=" + msg
        requests.get(tg_send_msg_url)
        return 0

    if res.status_code == 200:
        result = res.json()
        print(result)

        if result:
            if result["header"]["desc"] == "success":
                sum = result["body"]["data"][0]["result"]["sum"]  # IP总数
                sum1 = sum[0][0]
                sum2 = sum[1][0]
                print("sum:" + str(sum))
                print("sum1:" + str(sum1))
                print("sum2:" + str(sum2))





                data = result["body"]["data"][0]["result"]["items"]
                today = data[1]
                yesterday = data[2]
                today.reverse()
                yesterday.reverse()
                print("+++++++++++")
                print("today")
                print(today)
                print("yesterday")
                print(yesterday)
                today_data = []
                yesterday_data = []
                for i in range(24):
                    ip = today[i][1]
                    if str(ip) == '--': #when the Ip is not available, it is --
                        ip = 0
                    today_data.append(ip)
                    ip = yesterday[i][1]
                    yesterday_data.append(ip)
        print("+++++++++++")
        print("today data:")
        #print(today_data)
        print("+++++++++++")
        print("yesterday data:")
        #print(yesterday_data)

        xyDataList = []

        xData = hours
        yData = today_data
        xyData = {"legend": "昨日-" + str_yesterday + site + "-总IP:"+str(sum1), "xData": xData, "yData": yData}
        xyDataList.append(xyData)

        xData = hours
        yData = yesterday_data
        xyData = {"legend": "前日-" + str_bef_yesterday + site + "-总IP:"+str(sum2), "xData": xData, "yData": yData}
        xyDataList.append(xyData)

        save_path = "baidu_ips_yesterday" + site + ".jpg"
        plot_save(xyDataList, save_path)
        img = open(save_path, 'rb')


        bot.sendPhoto(chat_id, photo=open(save_path, 'rb'))

        #chat_id = "-720651316"  # maomi_三站汇报
        #bot.sendPhoto(chat_id, photo=open(save_path, 'rb'))

        img.close()










if __name__ == "__main__":

    baidu(str_yesterday,str_bef_yesterday)
    #baidu(str_lastweek)





















