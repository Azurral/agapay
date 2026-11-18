"""Colour themes (resources/css/app.css): the brand-coloured icons, drawn once per theme.

The violet originals stay where they are (public/images/figma/icons/*.svg); each other theme gets a copy with only the
colour swapped, so the shapes stay identical. (Navy & Gold and Green & Cream are flat, so they need no gradient
images.) Run again after changing a theme's colours:
    python scripts/theme-assets.py
"""
import os

ROOT = os.path.join(os.path.dirname(__file__), '..', 'public', 'images', 'figma')

# The icons' brand-soft colour in Violet & Blue.
VIOLET_ICON = '#7E80FF'

THEMES = {
    'navy': {'icon': '#2C4A96'},    # Navy & Gold (LGU Bontoc): the lighter navy
    'green': {'icon': '#1F7A3A'},   # Green & Cream (Department of Agriculture)
}


def write(path: str, text: str) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8', newline='') as f:
        f.write(text)


for theme, colours in THEMES.items():
    for name in ['task', 'plus', 'calendar', 'ring']:
        svg = open(os.path.join(ROOT, 'icons', name + '.svg'), encoding='utf-8').read()
        write(os.path.join(ROOT, 'icons', theme, name + '.svg'), svg.replace(VIOLET_ICON, colours['icon']))
    print(theme, 'done')
