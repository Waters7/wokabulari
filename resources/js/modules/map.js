// Peta Leaflet + OpenStreetMap untuk memilih titik lokasi dan radius geofence.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/**
 * Pasang peta pemilih titik.
 *
 * @param {HTMLElement} el
 * @param {{lat:number,lng:number,radius:number,editable?:boolean,onChange?:(lat:number,lng:number)=>void}} options
 */
export function createPicker(el, { lat, lng, radius, editable = true, onChange = () => {} }) {
    const map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 17);
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

    const style = { color: '#2E7D4F', fillColor: '#5DB57B', fillOpacity: 0.2, weight: 2 };
    const circle = L.circle([lat, lng], { ...style, radius }).addTo(map);
    const marker = L.circleMarker([lat, lng], { radius: 8, color: '#123B2D', fillColor: '#F2A81D', fillOpacity: 1, weight: 2 }).addTo(map);

    const move = (newLat, newLng) => {
        marker.setLatLng([newLat, newLng]);
        circle.setLatLng([newLat, newLng]);
    };

    if (editable) {
        map.on('click', (event) => {
            const { lat: newLat, lng: newLng } = event.latlng;
            move(newLat, newLng);
            onChange(Number(newLat.toFixed(7)), Number(newLng.toFixed(7)));
        });
    }

    // Peta di dalam modal perlu dihitung ulang ukurannya setelah tampil.
    setTimeout(() => map.invalidateSize(), 250);

    return {
        setRadius(value) {
            const r = Number(value);
            if (r > 0) {
                circle.setRadius(r);
            }
        },
        setLatLng(newLat, newLng) {
            move(newLat, newLng);
            map.panTo([newLat, newLng]);
        },
        destroy() {
            map.remove();
        },
    };
}

/**
 * Peta baca-saja dengan beberapa titik (dipakai di dashboard & detail absen).
 *
 * @param {HTMLElement} el
 * @param {Array<{lat:number,lng:number,radius?:number,label?:string,color?:string}>} points
 */
export function createViewer(el, points) {
    const first = points[0] ?? { lat: 1.4605, lng: 124.8228 };
    const map = L.map(el, { scrollWheelZoom: false }).setView([first.lat, first.lng], 16);
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(map);

    const bounds = [];
    for (const point of points) {
        const color = point.color ?? '#2E7D4F';
        if (point.radius) {
            L.circle([point.lat, point.lng], { radius: point.radius, color, fillOpacity: 0.15, weight: 2 }).addTo(map);
        }
        const marker = L.circleMarker([point.lat, point.lng], { radius: 7, color: '#123B2D', fillColor: color, fillOpacity: 1, weight: 2 }).addTo(map);
        if (point.label) {
            marker.bindTooltip(point.label);
        }
        bounds.push([point.lat, point.lng]);
    }

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }

    setTimeout(() => map.invalidateSize(), 250);

    return map;
}
