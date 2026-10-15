# Paramètres à modifier : taux et prix, puis relancer `python3 gen.py`
RATE = 1.16            # 1 EUR = 1,16 USD (indicatif, à remplacer par le taux du jour)
DATE = "15 octobre 2026"
LINES = [  # (désignation, détail, qté, PU €/unité, unité)
 ("Hébergement mutualisé « Performance »", "Site de présentation · LWS · aphmedi.com", 12, 9.99, "mois"),
 ("Hébergement mutualisé « Starter »", "LWS · 12 mois (prix indicatif à confirmer)", 12, 3.49, "mois"),
 ("Nom de domaine aphmedi.com", "1re année · SSL Let's Encrypt inclus", 1, 6.99, "an"),
]
def fe(x): return f"{x:,.2f}".replace(",", " ").replace(".", ",") + " €"
def fu(x): return "$" + f"{x:,.2f}"
rows=""; te=0; tu=0
for d,s,q,pu,u in LINES:
    e=round(q*pu,2); us=round(e*RATE,2); te+=e; tu+=us
    rows+=f"<tr><td><b>{d}</b><small>{s}</small></td><td class=c>{q} {u}{'s' if q>1 and u=='an' else ''}</td><td class=r>{fe(pu)}<small>{fu(round(pu*RATE,2))}</small></td><td class=r>{fe(e)}</td><td class=r><b>{fu(us)}</b></td></tr>"
html=f"""<!doctype html><html lang=fr><head><meta charset=utf-8><title>Facture APHMEDI</title><style>
@page{{size:A4;margin:0}}*{{box-sizing:border-box;margin:0;padding:0}}
body{{font-family:'Helvetica Neue',Arial,sans-serif;color:#1c2340;width:210mm;height:297mm;padding:16mm 16mm 12mm;font-size:11pt;position:relative}}
.top{{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:4px solid #4b2ea8;padding-bottom:10mm}}
h1{{font-size:34pt;letter-spacing:3px;color:#4b2ea8}}.brand{{font-size:20pt;font-weight:800}}
.meta{{text-align:right;line-height:1.6}}.draft{{display:inline-block;background:#fff1cf;color:#8a5a00;font-weight:700;padding:2px 10px;border-radius:4px;font-size:9pt}}
.parties{{display:flex;gap:10mm;margin:10mm 0}}.box{{flex:1;background:#f4f2fc;border-radius:8px;padding:5mm;line-height:1.55}}
.box h3{{font-size:9pt;letter-spacing:2px;color:#4b2ea8;margin-bottom:2mm}}.ph{{color:#9a8fc9}}
table{{width:100%;border-collapse:collapse}}th{{background:#4b2ea8;color:#fff;font-size:9pt;letter-spacing:1px;padding:3mm;text-align:left}}
td{{padding:4mm 3mm;border-bottom:1px solid #e3e0f2;vertical-align:top}}td small{{display:block;color:#6b7194;font-size:8.5pt;margin-top:1mm}}
.r{{text-align:right}}.c{{text-align:center}}th.r{{text-align:right}}th.c{{text-align:center}}
.tot{{margin:8mm 0 0 auto;width:92mm;line-height:2}}.tot div{{display:flex;justify-content:space-between}}
.tot .g{{background:#4b2ea8;color:#fff;border-radius:6px;padding:0 4mm;font-weight:800;font-size:13pt;margin-top:2mm}}
.note{{margin-top:9mm;font-size:9.5pt;line-height:1.6;background:#fff8e6;border-left:4px solid #f0a020;padding:4mm 5mm;border-radius:4px}}
.note b{{color:#8a5a00}}.foot{{position:absolute;left:16mm;right:16mm;bottom:10mm;font-size:8.5pt;color:#6b7194;text-align:center;border-top:1px solid #e3e0f2;padding-top:3mm}}
</style></head><body>
<div class=top><div><h1>FACTURE</h1><div class=brand>APHMEDI</div></div>
<div class=meta>N° <b>FAC-2026-1015-001</b><br>Date : {DATE}<br>Échéance : 15 novembre 2026<br><small style="color:#6b7194">(paiement sous 1 mois)</small></div></div>
<div class=parties><div class=box><h3>ÉMETTEUR</h3><b>Gloire ILUNGA</b><br>Développeur et ingénieur logiciel<br>gloireilunga703@gmail.com<br><span class=ph>[Adresse / téléphone, facultatif]</span></div>
<div class=box><h3>CLIENT</h3><b>Entreprise APHMEDI</b><br>À l'attention de M. Joseph MUKUNA<br>(responsable du paiement)<br><span class=ph>[Adresse / contact, facultatif]</span></div></div>
<table><tr><th>DÉSIGNATION</th><th class=c>PÉRIODE</th><th class=r>PRIX UNIT. HT</th><th class=r>TOTAL HT (EUR)</th><th class=r>TOTAL HT (USD)</th></tr>{rows}</table>
<div class=tot><div><span>Total HT</span><span>{fe(te)}</span></div><div><span>TVA</span><span class=ph>selon pays</span></div>
<div class=g><span>TOTAL HT en USD</span><span>{fu(tu)}</span></div></div>
<div class=note><b>Taux de conversion :</b> 1 € = {str(RATE).replace('.',',')} $ (taux indicatif, à vérifier avant envoi). Les prix en euros sont ceux de la proposition d'hébergement APHMEDI d'octobre 2026 (LWS), hors taxes ; le prix Starter est à confirmer sur lws.fr.<br>
<b>Année suivante :</b> passage prévu à une formule d'hébergement supérieure. Le renouvellement du nom de domaine est estimé à environ 9,99 € HT/an ; les tarifs de la nouvelle formule seront établis sur une facture distincte.</div>
<div class=foot>APHMEDI · Facture d'hébergement (LWS) et de nom de domaine · aphmedi.com</div></body></html>"""
open("facture.html","w").write(html)
from playwright.sync_api import sync_playwright
import glob
exe=glob.glob("/opt/pw-browsers/chromium-*/chrome-linux/chrome")[0]
with sync_playwright() as p:
    b=p.chromium.launch(executable_path=exe,args=["--no-sandbox"]); pg=b.new_page()
    pg.goto("file:///home/user/index/facture/facture.html"); pg.pdf(path="facture.pdf",prefer_css_page_size=True,print_background=True); b.close()
print(fe(te), fu(tu))
