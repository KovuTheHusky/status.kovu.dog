// generate-sprite.js
import Spritesmith from "spritesmith";
import { glob } from "glob";
import fs from "fs/promises";
import path from "path";
import { promisify } from "util";

const inputDir = "markers";
const outputFile = "sprite";

async function buildSprite() {
  try {
    // 1. Find all .png files in the markers directory
    const files = await glob(`${inputDir}/*.png`);
    if (files.length === 0) {
      console.log(
        `No images found in '${inputDir}', skipping sprite generation.`
      );
      return;
    }

    // 2. Run spritesmith
    const spritesmithRun = promisify(Spritesmith.run.bind(Spritesmith));
    const result = await spritesmithRun({ src: files });

    // 3. The result contains the image data and coordinates.
    // We need to format the coordinates to match the Mapbox sprite JSON format.
    const mapboxJson = {};
    for (const [filepath, coords] of Object.entries(result.coordinates)) {
      const iconId = path.basename(filepath, ".png");
      mapboxJson[iconId] = {
        ...coords,
        pixelRatio: 1, // Add the required pixelRatio property
      };
    }

    // 4. Write the sprite image and the JSON file
    await fs.writeFile(`${outputFile}.png`, result.image);
    await fs.writeFile(
      `${outputFile}.json`,
      JSON.stringify(mapboxJson, null, 2)
    );

    console.log(
      `Sprite sheet generated successfully: ${outputFile}.png, ${outputFile}.json`
    );
  } catch (err) {
    console.error("Error generating sprite sheet:", err);
    process.exit(1);
  }
}

buildSprite();
