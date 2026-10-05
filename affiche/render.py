from playwright.sync_api import sync_playwright
import glob
exe=glob.glob("/opt/pw-browsers/chromium-*/chrome-linux/chrome")[0]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=exe,args=["--no-sandbox"])
    pg=b.new_page(viewport={"width":1080,"height":1500})
    pg.goto("file:///home/user/index/affiche/affiche.html"); pg.wait_for_timeout(1500)
    pg.screenshot(path="/home/user/index/affiche/affiche.png"); b.close()
