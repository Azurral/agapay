"""Colour themes (resources/css/app.css): the gradient backgrounds and the brand-coloured icons, drawn once per theme.

The violet originals stay where they are (public/images/figma/gradients/*.svg, icons/*.svg); each other theme gets a
copy with only the colours swapped, so the shapes stay identical. Run again after changing a theme's colours:
    python scripts/theme-assets.py
"""
import os

ROOT = os.path.join(os.path.dirname(__file__), '..', 'public', 'images', 'figma')

# The four stops of the violet gradient (brand -> pale), and the icons' brand-soft colour.
VIOLET_STOPS = ['rgba(90,93,227,1)', 'rgba(120,118,234,1)', 'rgba(150,144,241,1)', 'rgba(210,196,255,1)']
VIOLET_ICON = '#7E80FF'

THEMES = {
    # Navy & Gold (LGU Bontoc): navy to a pale periwinkle; icons in the lighter navy.
    'navy': {'stops': ['rgba(46,42,134,1)', 'rgba(70,66,166,1)', 'rgba(107,103,196,1)', 'rgba(190,188,236,1)'], 'icon': '#4A46B5'},
    # Green & Cream (Department of Agriculture): dark green to the light green of the DA tabs.
    'green': {'stops': ['rgba(17,105,46,1)', 'rgba(38,128,60,1)', 'rgba(84,160,88,1)', 'rgba(193,242,176,1)'], 'icon': '#1F7A3A'},
}


def write(path: str, text: str) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8', newline='') as f:
        f.write(text)


for theme, colours in THEMES.items():
    for name in ['bar', 'button', 'card', 'login', 'logout']:
        svg = open(os.path.join(ROOT, 'gradients', name + '.svg'), encoding='utf-8').read()
        for old, new in zip(VIOLET_STOPS, colours['stops']):
            svg = svg.replace(old, new)
        write(os.path.join(ROOT, 'gradients', theme, name + '.svg'), svg)
    for name in ['task', 'plus', 'calendar', 'ring']:
        svg = open(os.path.join(ROOT, 'icons', name + '.svg'), encoding='utf-8').read()
        write(os.path.join(ROOT, 'icons', theme, name + '.svg'), svg.replace(VIOLET_ICON, colours['icon']))
    print(theme, 'done')
