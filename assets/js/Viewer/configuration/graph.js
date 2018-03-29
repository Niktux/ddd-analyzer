export const options = {
    layout: {
        hierarchical: false
    },
    physics: {
        repulsion: {
            centralGravity: 2.35,
            springLength: 130,
            springConstant: 0.18,
            nodeDistance: 400,
            damping: 1
        },
        solver: "repulsion",
    },
    nodes: {
        shape: 'dot',
        scaling: {
            customScalingFunction: function (min,max,total,value) {
              return value/total;
            },
            min:15,
            max:80,
        }
    },
    edges: {
        color: "#000000",
        scaling: {
            customScalingFunction: function (min,max,total,value) {
              return value/total;
            },
            min:3,
            max:30
        },
        smooth: {
            type: 'curvedCW',
            roundness: 0.2,
        }
    }
}

export const dependenciesThreshold = [
    {
        min: 0,
        max: 4,
        color: '#DFF2BF',
    },
    {
        min: 5,
        max: 9,
        color: '#FEEFB3',
    },
    {
        min: 10,
        max: 14,
        color: '#FEDAB3',
    },
    {
        min: 15,
        max: 9999999999,
        color: '#FFD2D2',
    },
]
